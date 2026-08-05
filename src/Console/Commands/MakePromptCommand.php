<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PromptPHP\Deck\Concerns\ReadsJsonFiles;

class MakePromptCommand extends Command
{
    use ReadsJsonFiles;

    protected $signature = 'make:prompt {name? : The name of the prompt}
                            {--from= : Path to a stub file to use as template}
                            {--desc= : A short description of what this prompt does}
                            {--u|user : Also create a user prompt file}
                            {--role=* : Additional roles to create prompt files for (e.g. assistant, developer, tool)}
                            {--i|interactive : Interactively choose additional roles}
                            {--f|force : Overwrite existing prompt}';

    protected $description = 'Create a new prompt structure for your AI agent';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $promptedForName = ! $this->argument('name');
        $rawName         = $this->argument('name') ?? $this->ask('What should the prompt be named?');

        if (! $rawName) {
            $this->error('A prompt name is required.');

            return Command::FAILURE;
        }

        $name     = $this->toKebabCase($rawName);
        $basePath = config('deck.path');

        // Resolve description.
        $description = $this->option('desc')
            ?? ($promptedForName ? ($this->ask('Briefly describe this prompt (press Enter to skip)') ?? '') : '');

        if (! is_dir($basePath)) {
            $this->files->makeDirectory($basePath, 0755, true);
        }

        $promptPath = "{$basePath}/{$name}";

        // Resolve which version to create.
        [$version, $versionPath] = $this->resolveVersion($promptPath, $name);

        if ($version === 0) {
            return Command::FAILURE;
        }

        // Create directories (skip if they already exist, e.g. when --force or -f is used).
        if (! $this->files->isDirectory($versionPath)) {
            $this->files->makeDirectory($versionPath, 0755, true);
        }

        // Determine file extension
        $extension = config('deck.extension', 'md');

        // Create system prompt file (always created by default).
        $systemFile = "{$versionPath}/system.{$extension}";
        $this->files->put($systemFile, $this->getSystemStubContent());

        // Create user prompt file when --user is passed or interactively confirmed.
        $createUser = $this->option('user')
            || ($promptedForName && $this->confirm('Would you also like to create a user prompt file?'));

        if ($createUser) {
            $userFile    = "{$versionPath}/user.{$extension}";
            $stubContent = $this->getStubContent($this->option('from'));
            $this->files->put($userFile, $stubContent);
        }

        // Handle additional roles (auto-interactive when name was prompted).
        $roles = $this->resolveExtraRoles($promptedForName);

        foreach ($roles as $role) {
            $roleName = $this->toKebabCase($role);
            $roleFile = "{$versionPath}/{$roleName}.{$extension}";
            $this->files->put($roleFile, $this->getRoleStubContent($roleName));
        }

        // Build the role list for this version.
        $allRoles = ['system'];

        if ($createUser) {
            $allRoles[] = 'user';
        }

        $allRoles = array_values(array_merge($allRoles, array_map([$this, 'toKebabCase'], $roles)));

        // Merge into the prompt-level metadata rather than replacing it, so keys
        // this command does not own survive — above all `active_version`, which
        // decides which version the application actually serves.
        $existing = $this->readJson("{$promptPath}/metadata.json");

        $metadata = array_merge($existing, [
            'name'        => $name,
            'description' => $description !== '' ? $description : ($existing['description'] ?? ''),
            'roles'       => $allRoles,
            'variables'   => $existing['variables'] ?? [],
            'created_at'  => $existing['created_at'] ?? now()->toIso8601String(),
        ]);

        $this->files->put("{$promptPath}/metadata.json", json_encode($metadata, JSON_PRETTY_PRINT));

        // Write this version's own metadata — the file PromptManager reads when
        // resolving a template's metadata.
        $this->files->put("{$versionPath}/metadata.json", json_encode([
            'version'    => $version,
            'roles'      => $allRoles,
            'created_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));

        $roleList = implode(', ', $allRoles);
        $this->info("Version {$version} of the [{$name}] prompt has been created successfully with the following roles: {$roleList}.");

        $this->hintActivation($name, $version, $existing['active_version'] ?? null);

        return Command::SUCCESS;
    }

    /**
     * Tell the user how to promote the version just created.
     *
     * Scaffolding a version deliberately leaves the active version alone, so
     * without this hint a newly created version can look like it did nothing.
     */
    protected function hintActivation(string $name, int $version, mixed $activeVersion): void
    {
        if ($activeVersion === null || (int) $activeVersion === $version) {
            return;
        }

        $this->newLine();
        $this->comment("v{$activeVersion} is still the active version.");
        $this->comment("Run `php artisan prompt:activate {$name} v{$version}` to make v{$version} live.");
    }

    /**
     * Determine which version directory to create.
     *
     * - Brand-new prompt → v1.
     * - Existing prompt + --force → overwrite the latest version.
     * - Existing prompt (interactive) → ask whether to create a new
     *   version or overwrite the latest.
     *
     * Returns [0, ''] when the user cancels.
     *
     * @return array{int, string}
     */
    protected function resolveVersion(string $promptPath, string $name): array
    {
        $latestVersion = $this->detectLatestVersion($promptPath);

        // Brand-new prompt — start at v1.
        if ($latestVersion === 0) {
            return [1, "{$promptPath}/v1"];
        }

        // Non-interactive with --force → overwrite the latest version.
        if ($this->option('force')) {
            return [$latestVersion, "{$promptPath}/v{$latestVersion}"];
        }

        // Prompt already exists — guide the user.
        $this->warn("Prompt [{$name}] already exists at version {$latestVersion}.");

        $choice = $this->choice(
            'What would you like to do?',
            [
                'version'   => 'Create a new version (v'.($latestVersion + 1).')',
                'overwrite' => "Overwrite version {$latestVersion}",
                'cancel'    => 'Cancel',
            ],
            'version',
        );

        return match ($choice) {
            'version'   => [$latestVersion + 1, "{$promptPath}/v".($latestVersion + 1)],
            'overwrite' => [$latestVersion, "{$promptPath}/v{$latestVersion}"],
            default     => [0, ''],
        };
    }

    /**
     * Detect the highest existing version number for a prompt.
     *
     * Returns 0 if the prompt directory does not exist or has no versions.
     */
    protected function detectLatestVersion(string $promptPath): int
    {
        if (! $this->files->isDirectory($promptPath)) {
            return 0;
        }

        $latest = 0;

        foreach ($this->files->directories($promptPath) as $dir) {
            // Anchored against the directory name alone, matching
            // PromptManager::versions(). The two must agree on what counts as
            // a version, or the command scaffolds over an existing one.
            if (preg_match('/^v(\d+)$/', basename($dir), $matches)) {
                $latest = max($latest, (int) $matches[1]);
            }
        }

        return $latest;
    }

    protected function getStubContent(?string $customStub): string
    {
        if ($customStub && $this->files->exists($customStub)) {
            return $this->files->get($customStub);
        }

        return $this->files->get($this->resolveStubPath('user-prompt.stub'));
    }

    protected function getSystemStubContent(): string
    {
        return $this->files->get($this->resolveStubPath('system-prompt.stub'));
    }

    /**
     * Get the rendered content for an extra role prompt file.
     *
     * The generic role stub's {{ $role }} placeholder is replaced at
     * scaffolding time so each file is personalised to its role.
     */
    protected function getRoleStubContent(string $role): string
    {
        $content = $this->files->get($this->resolveStubPath('role-prompt.stub'));

        return str_replace('{{ $role }}', $role, $content);
    }

    /**
     * Determine the extra roles to scaffold.
     *
     * Uses the --role option values when provided; otherwise, in an
     * interactive terminal, prompts the user with a free-text input.
     *
     * @return list<string>
     */
    protected function resolveExtraRoles(bool $autoInteractive = false): array
    {
        /** @var list<string> $roles */
        $roles = $this->option('role');

        if (! empty($roles)) {
            return array_values(array_filter(array_map('trim', $roles)));
        }

        if (! $autoInteractive && ! $this->option('interactive')) {
            return [];
        }

        if (! $this->confirm('Would you like to create prompt files for additional roles?')) {
            return [];
        }

        $input = $this->ask('Which roles? (comma-separated, e.g. assistant,developer)');

        if (! $input) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $input))));
    }

    /**
     * Resolve the path to a stub file.
     *
     * If the user has published stubs, use those; otherwise fall back to
     * the package defaults.
     */
    protected function resolveStubPath(string $stub): string
    {
        $publishedPath = $this->laravel->basePath("stubs/deck/{$stub}");

        if ($this->files->exists($publishedPath)) {
            return $publishedPath;
        }

        return __DIR__.'/../../../stubs/'.$stub;
    }

    /**
     * Convert a string to kebab-case.
     *
     * Handles PascalCase, camelCase, snake_case, and mixed inputs.
     */
    protected function toKebabCase(string $value): string
    {
        // Insert a hyphen before uppercase letters that follow a lowercase letter or digit.
        $value = (string) preg_replace('/([a-z\d])([A-Z])/', '$1-$2', $value);

        // Insert a hyphen between consecutive uppercase letters followed by a lowercase letter (e.g. "XMLParser" → "XML-Parser").
        $value = (string) preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1-$2', $value);

        // Replace underscores, spaces, and multiple hyphens with a single hyphen.
        $value = (string) preg_replace('/[_\s]+/', '-', $value);
        $value = (string) preg_replace('/-{2,}/', '-', $value);

        return strtolower(trim($value, '-'));
    }
}
