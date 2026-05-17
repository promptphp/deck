<?php

declare(strict_types=1);

namespace PromptPHP\Deck;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use PromptPHP\Deck\Exceptions\InvalidVersionException;
use PromptPHP\Deck\Exceptions\PromptNotFoundException;

class PromptManager
{
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
     *   Deck::get('order-summary')        // active version.
     *   Deck::get('order-summary', 2)     // specific version.
     */
    public function get(string $name, ?int $version = null): PromptTemplate
    {
        $version ??= $this->getActiveVersion($name);
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
     */
    public function activate(string $name, int $version): bool
    {
        // Store active version in database or config file?
        // For simplicity, we'll store in a JSON file or use the database if tracking is enabled.
        // We'll assume we have a "prompt_versions" table with an "is_active" column.
        if ($this->trackingConfig['enabled'] ?? false) {
            // Update database.
            DB::connection($this->trackingConfig['connection'] ?? config('database.default'))
                ->table('prompt_versions')
                ->where('name', $name)
                ->update(['is_active' => false]);

            return DB::table('prompt_versions')
                ->where('name', $name)
                ->where('version', $version)
                ->update(['is_active' => true]) > 0;
        }

        // Fallback: store in a JSON file in the prompt directory.
        $metadataFile = "{$this->basePath}/{$name}/metadata.json";
        $metadata     = $this->files->exists($metadataFile)
            ? json_decode($this->files->get($metadataFile), true)
            : [];

        $metadata['active_version'] = $version;

        $this->files->put($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT));

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
        $metadataFile = "{$this->basePath}/{$name}/metadata.json";

        if ($this->files->exists($metadataFile)) {
            $metadata = json_decode($this->files->get($metadataFile), true);

            if (isset($metadata['active_version'])) {
                return (int) $metadata['active_version'];
            }
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
    protected function loadFromFiles(string $name, int $version): array
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

        // Load metadata.json if present.
        $metadata = [];

        if ($this->files->exists($metaFile = "{$versionPath}/metadata.json")) {
            $metadata = json_decode($this->files->get($metaFile), true) ?? [];
        }

        return [
            'roles'    => $roles,
            'metadata' => $metadata,
        ];
    }

    /**
     * Load metadata for a specific prompt version.
     */
    protected function loadMetadata(string $name, int $version): array
    {
        $metaFile = "{$this->basePath}/{$name}/v{$version}/metadata.json";

        if ($this->files->exists($metaFile)) {
            return json_decode($this->files->get($metaFile), true) ?? [];
        }

        return [];
    }
}
