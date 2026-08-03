<?php

declare(strict_types=1);

namespace PromptPHP\Deck;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use PromptPHP\Deck\Concerns\ReadsJsonFiles;
use PromptPHP\Deck\Concerns\ResolvesVersion;
use PromptPHP\Deck\Exceptions\InvalidVersionException;
use PromptPHP\Deck\Exceptions\PromptNotFoundException;

class PromptManager
{
    use ReadsJsonFiles;
    use ResolvesVersion;

    protected Filesystem $files;

    protected string $basePath;

    protected string $extension;

    protected Cache $cache;

    protected Config $config;

    protected ?array $trackingConfig;

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
        return $this->get($name, $this->getActiveVersion($name));
    }

    /**
     * List all versions for a prompt.
     */
    public function versions(string $name): array
    {
        $promptPath = "{$this->basePath}/{$name}";

        if (! $this->files->isDirectory($promptPath)) {
            throw PromptNotFoundException::named($name);
        }

        $versions = [];

        // Scan for version directories (v1, v2, etc.) or version files.
        $items = $this->files->directories($promptPath);

        foreach ($items as $dir) {
            if (preg_match('/v(\d+)$/', $dir, $matches)) {
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
        $version = $this->parseVersion((string) $version)
            ?? throw InvalidVersionException::unparseable($name, $version);

        $this->ensureVersionExists($name, $version);

        if ($this->trackingConfig['enabled'] ?? false) {
            $connection = DB::connection(
                $this->trackingConfig['connection'] ?? config('database.default')
            );

            // Update database.
            $connection->transaction(function () use ($connection, $name, $version): void {
                $connection
                    ->table('prompt_versions')
                    ->where('name', $name)
                    ->update(['is_active' => false]);

                $connection
                    ->table('prompt_versions')
                    ->where('name', $name)
                    ->where('version', $version)
                    ->update(['is_active' => true]);
            });
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
     */
    public function track(string $promptName, int $version, array $data): void
    {
        if (! ($this->trackingConfig['enabled'] ?? false)) {
            return;
        }

        DB::connection($this->trackingConfig['connection'] ?? config('database.default'))
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
    }

    /**
     * Get the active version number for a prompt.
     */
    protected function getActiveVersion(string $name): int
    {
        // Check database first if tracking enabled.
        if ($this->trackingConfig['enabled'] ?? false) {
            $record = DB::connection($this->trackingConfig['connection'] ?? config('database.default'))
                ->table('prompt_versions')
                ->where('name', $name)
                ->where('is_active', true)
                ->first();

            if ($record) {
                return $record->version;
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
