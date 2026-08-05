<?php

declare(strict_types=1);

namespace PromptPHP\Deck;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PromptPHP\Deck\Concerns\ReadsJsonFiles;
use PromptPHP\Deck\Concerns\ResolvesVersion;
use PromptPHP\Deck\Exceptions\InvalidPromptNameException;
use PromptPHP\Deck\Exceptions\InvalidVersionException;
use PromptPHP\Deck\Exceptions\PromptNotFoundException;
use Throwable;

class PromptManager
{
    use ReadsJsonFiles;
    use ResolvesVersion;

    /**
     * Characters permitted in a prompt name.
     *
     * Names are interpolated into filesystem paths, so anything that could
     * escape the prompts directory is rejected. A leading dot is disallowed,
     * which also rules out '..'.
     */
    protected const NAME_PATTERN = '/^[A-Za-z0-9_-][A-Za-z0-9._-]*$/';

    protected Filesystem $files;

    protected string $basePath;

    protected string $extension;

    protected Cache $cache;

    protected Config $config;

    protected ?array $trackingConfig;

    /**
     * Whether the tracking database has been found unusable this instance.
     *
     * Latched on the first failure so a missing table or unreachable host is
     * attempted once rather than on every prompt load — an unreachable host
     * would otherwise cost a connection timeout per render.
     *
     * The manager is a singleton, so this is once per request under FPM. Under
     * Octane or a queue worker it lives for the worker's lifetime, so a
     * misconfigured worker warns once and then stays quiet.
     */
    protected bool $trackingUnavailable = false;

    public function __construct(string $basePath, string $extension, Cache $cache, Config $config)
    {
        $this->files          = new Filesystem;
        $this->basePath       = rtrim($basePath, '/');
        $this->extension      = ltrim($extension, '.');
        $this->cache          = $cache;
        $this->config         = $config;
        $this->trackingConfig = $config->get('deck.tracking');
    }

    /**
     * Get a prompt instance by name and optional version.
     * If version is not provided, the active version will be used.
     *
     * Deck::get('order-summary')        // active version.
     * Deck::get('order-summary', 'v2')  // specific version.
     * Deck::get('order-summary', 2)     // specific version.
     */
    public function get(string $name, string|int|null $version = null): PromptTemplate
    {
        $this->assertValidName($name);

        if ($version === null) {
            $version = $this->getActiveVersion($name);
        } else {
            $version = $this->parseVersion((string) $version)
                ?? throw InvalidVersionException::unparseable($name, $version);
        }

        $cacheKey = $this->config->get('deck.cache.prefix', 'deck:')."{$name}.v{$version}";

        // Attempt to load from cache.
        if ($this->config->get('deck.cache.enabled')) {
            $cached = $this->cache->get($cacheKey);

            if ($cached) {
                return new PromptTemplate(
                    $name,
                    $version,
                    $cached['roles'] ?? [],
                    $cached['metadata'] ?? []
                );
            }
        }

        // Load from filesystem.
        $promptData = $this->loadFromFiles($name, $version);

        // Cache if enabled.
        if ($this->config->get('deck.cache.enabled')) {
            $this->cache->put($cacheKey, $promptData, now()->addSeconds($this->config->get('deck.cache.ttl')));
        }

        return new PromptTemplate(
            $name,
            $version,
            $promptData['roles'] ?? [],
            $promptData['metadata'] ?? []
        );
    }

    /**
     * Get the active version of a prompt.
     */
    public function active(string $name): PromptTemplate
    {
        $this->assertValidName($name);

        return $this->get($name, $this->getActiveVersion($name));
    }

    /**
     * List all versions for a prompt.
     */
    public function versions(string $name): array
    {
        $this->assertValidName($name);

        $promptPath = "{$this->basePath}/{$name}";

        if (! $this->files->isDirectory($promptPath)) {
            throw PromptNotFoundException::named($name);
        }

        $versions = [];

        // Scan for version directories (v1, v2, etc.).
        $items = $this->files->directories($promptPath);

        foreach ($items as $dir) {
            // Anchored against the directory name alone: an unanchored match
            // on the full path treats 'rev2', 'dev3' and 'archive-v9' as
            // versions 2, 3 and 9.
            if (preg_match('/^v(\d+)$/', basename($dir), $matches)) {
                $version    = (int) $matches[1];
                $versions[] = [
                    'version'  => $version,
                    'path'     => $dir,
                    'metadata' => $this->loadMetadata($name, $version),
                ];
            }
        }

        usort($versions, fn ($a, $b) => $a['version'] <=> $b['version']);

        return $versions;
    }

    /**
     * Activate a specific version.
     *
     * Accepts the same version formats as get(), so both of these work:
     *
     *   Deck::activate('order-summary', 'v2')
     *   Deck::activate('order-summary', 2)
     *
     * The active version is recorded in the database when tracking is enabled,
     * and in the prompt's root metadata.json either way.
     *
     * @param string|int $version The version to activate, e.g. 2 or 'v2'.
     *
     * @throws InvalidVersionException if the version cannot be parsed.
     */
    public function activate(string $name, string|int $version): bool
    {
        $this->assertValidName($name);

        $version = $this->parseVersion((string) $version)
            ?? throw InvalidVersionException::unparseable($name, $version);

        $this->ensureVersionExists($name, $version);

        if ($connection = $this->trackingConnection()) {
            try {
                $connection->transaction(function () use ($connection, $name, $version): void {
                    // Deactivate every other version of this prompt.
                    $connection
                        ->table('prompt_versions')
                        ->where('name', $name)
                        ->update(['is_active' => false]);

                    // Record this one, inserting it if it has not been activated
                    // before. Timestamps are passed explicitly: this is a query
                    // builder call, so Eloquent does not maintain them.
                    $connection
                        ->table('prompt_versions')
                        ->updateOrInsert(
                            ['name' => $name, 'version' => $version],
                            ['is_active' => true, 'updated_at' => now(), 'created_at' => now()],
                        );
                });
            } catch (QueryException $e) {
                // Only a missing table is a degradable condition. A deadlock, a
                // constraint violation from concurrent activations, or a full
                // disk must surface: swallowing them would write metadata.json,
                // return true, and leave the database — which getActiveVersion()
                // prefers — still pointing at the previous version.
                if (! $this->trackingTableMissing($connection, 'prompt_versions')) {
                    throw $e;
                }

                $this->markTrackingUnavailable($e);
            }
        }

        // Fallback: store in a JSON file in the prompt directory.
        $metadataFile = "{$this->basePath}/{$name}/metadata.json";

        $metadata = $this->readJson($metadataFile);

        $metadata['active_version'] = $version;

        $this->files->put($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return true;
    }

    /**
     * Track an execution for performance monitoring.
     *
     * Never throws. This runs after a completed — and paid for — AI call, so
     * no analytics failure is worth destroying the response that triggered it.
     */
    public function track(string $promptName, int $version, array $data): void
    {
        $this->assertValidName($promptName);

        if (! ($connection = $this->trackingConnection())) {
            return;
        }

        try {
            $connection
                ->table('prompt_executions')
                ->insert([
                    'prompt_name'    => $promptName,
                    'prompt_version' => $version,
                    'input'          => json_encode($data['input'] ?? null),
                    'output'         => $data['output'] ?? null,
                    'tokens'         => $data['tokens'] ?? null,
                    'latency_ms'     => $data['latency'] ?? null,
                    'cost'           => $data['cost'] ?? null,
                    'model'          => $data['model'] ?? null,
                    'provider'       => $data['provider'] ?? null,
                    'feedback'       => isset($data['feedback']) ? json_encode($data['feedback']) : null,
                    'created_at'     => now(),
                ]);
        } catch (Throwable $e) {
            // Deliberately broad: a missing table, an unreachable host, or a
            // json_encode failure on non-UTF-8 input must all be swallowed.
            $this->markTrackingUnavailable($e);
        }
    }

    /**
     * Get the active version number for a prompt.
     *
     * The database wins when it holds a record, so a version activated at
     * runtime takes precedence over the active_version committed alongside
     * the prompt files.
     */
    protected function getActiveVersion(string $name): int
    {
        // Check database first if tracking enabled.
        if ($connection = $this->trackingConnection()) {
            try {
                $record = $connection
                    ->table('prompt_versions')
                    ->where('name', $name)
                    ->where('is_active', true)
                    ->orderByDesc('version')
                    ->first();

                if ($record) {
                    return (int) $record->version;
                }
            } catch (QueryException $e) {
                // Rendering must survive any database problem, not only a
                // missing table: serving the version on disk beats not serving
                // at all. Latched, so this is attempted once per instance.
                $this->markTrackingUnavailable($e);
            }
        }

        // Fallback to metadata.json.
        $metadata = $this->readJson("{$this->basePath}/{$name}/metadata.json");

        if (isset($metadata['active_version'])) {
            return (int) $metadata['active_version'];
        }

        // If no active version set, return the highest version number.
        $versions = $this->versions($name);

        if (empty($versions)) {
            throw InvalidVersionException::noVersions($name);
        }

        return max(array_column($versions, 'version'));
    }

    /**
     * Resolve the connection tracking should write to, or null when tracking
     * is disabled or the database has already been found unusable.
     *
     * Catching Throwable is deliberate: an undefined DECK_DB_CONNECTION throws
     * InvalidArgumentException from the connection resolver rather than a
     * QueryException, and is a likely misconfiguration.
     */
    protected function trackingConnection(): ?Connection
    {
        if (! ($this->trackingConfig['enabled'] ?? false) || $this->trackingUnavailable) {
            return null;
        }

        try {
            return DB::connection($this->trackingConfig['connection'] ?? config('database.default'));
        } catch (Throwable $e) {
            $this->markTrackingUnavailable($e);

            return null;
        }
    }

    /**
     * Record that tracking is unusable, warning once for this instance.
     *
     * The latch keeps a broken database from being retried on every prompt
     * load, and doubles as the guard against flooding the log.
     */
    protected function markTrackingUnavailable(Throwable $e): void
    {
        if ($this->trackingUnavailable) {
            return;
        }

        $this->trackingUnavailable = true;

        Log::warning(
            'Deck tracking is enabled but the tracking database is unavailable. '
            .'Prompt rendering has fallen back to metadata.json. Publish and run '
            .'the Deck migrations, or set DECK_TRACKING_ENABLED=false. '
            .$e->getMessage()
        );
    }

    /**
     * Determine whether a tracking table is genuinely absent, as opposed to
     * present but erroring for some other reason.
     */
    protected function trackingTableMissing(Connection $connection, string $table): bool
    {
        try {
            return ! Schema::connection($connection->getName())->hasTable($table);
        } catch (Throwable) {
            // If the schema cannot be inspected either, the database is not in
            // a usable state — treat it as absent so callers degrade.
            return true;
        }
    }

    /**
     * Ensure a prompt name is safe to interpolate into a filesystem path.
     *
     * @throws InvalidPromptNameException
     */
    protected function assertValidName(string $name): void
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw InvalidPromptNameException::named($name);
        }
    }

    /**
     * Load prompt data from filesystem for a given name and version.
     *
     * Dynamically discovers all role files (*.{extension}) in the
     * version directory, so any role scaffolded by make:prompt is
     * automatically available at runtime.
     */
    protected function loadFromFiles(string $name, ?int $version): array
    {
        $versionPath = "{$this->basePath}/{$name}/v{$version}";

        if (! $this->files->isDirectory($versionPath)) {
            throw InvalidVersionException::forPrompt($name, $version);
        }

        $roles = [];

        // Scan every file matching the configured extension.
        foreach ($this->files->files($versionPath) as $file) {
            if ($file->getExtension() === $this->extension) {
                $roleName         = $file->getBasename('.'.$this->extension);
                $roles[$roleName] = $this->files->get($file->getPathname());
            }
        }

        return [
            'roles'    => $roles,
            'metadata' => $this->loadMetadata($name, $version),
        ];
    }

    /**
     * Load metadata for a specific prompt version.
     *
     * Prompt-level metadata (name, description, and anything else recorded in
     * the prompt's root metadata.json) forms the base, with the version's own
     * metadata.json layered on top so version-specific keys win.
     */
    protected function loadMetadata(string $name, ?int $version): array
    {
        return array_merge(
            $this->loadPromptMetadata($name),
            $this->readJson("{$this->basePath}/{$name}/v{$version}/metadata.json")
        );
    }

    /**
     * Load the prompt-level metadata shared by every version.
     *
     * `active_version` is stripped: it records which version the application
     * serves, which is prompt-level routing state rather than metadata about
     * the template being rendered.
     */
    protected function loadPromptMetadata(string $name): array
    {
        $metadata = $this->readJson("{$this->basePath}/{$name}/metadata.json");

        unset($metadata['active_version']);

        return $metadata;
    }

    /**
     * Ensure the specified prompt version exists before activation.
     *
     * @throws \InvalidArgumentException if the version is invalid or does not exist.
     */
    protected function ensureVersionExists(string $name, int $version): void
    {
        if ($version < 1) {
            throw new \InvalidArgumentException('The prompt version must be a positive integer.');
        }

        $promptPath = "{$this->basePath}/{$name}";

        if (! $this->files->isDirectory($promptPath)) {
            throw new \InvalidArgumentException("Prompt [{$name}] does not exist.");
        }

        $versionPath = "{$promptPath}/v{$version}";

        if (! $this->files->isDirectory($versionPath)) {
            throw new \InvalidArgumentException("Version [{$version}] does not exist for prompt [{$name}]. Create it first before activating.");
        }
    }
}
