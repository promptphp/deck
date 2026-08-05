<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use PromptPHP\Deck\PromptManager;
use PromptPHP\Deck\Providers\DeckServiceProvider;

test('PromptManager is registered as a singleton', function () {
    $instance1 = $this->app->make(PromptManager::class);
    $instance2 = $this->app->make(PromptManager::class);

    expect($instance1)->toBe($instance2);
});

test('PromptManager is resolvable via deck alias', function () {
    $instance = $this->app->make('deck');

    expect($instance)->toBeInstanceOf(PromptManager::class);
});

test('alias resolves to same singleton as class binding', function () {
    $byAlias = $this->app->make('deck');
    $byClass = $this->app->make(PromptManager::class);

    expect($byAlias)->toBe($byClass);
});

test('config is merged from package config file', function () {
    // The provider merges config/deck.php.
    // Our TestCase overrides some values but the merge should have happened.
    expect(config('deck.versioning'))->toBe('directory')
        ->and(config('deck.cache.ttl'))->toBe(3600);
});

test('Artisan commands are registered', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('make:prompt')
        ->and($commands)->toHaveKey('prompt:list')
        ->and($commands)->toHaveKey('prompt:activate')
        ->and($commands)->toHaveKey('prompt:diff')
        ->and($commands)->toHaveKey('prompt:test');
});

test('publishable config is registered', function () {
    // Verify the provider has registered publishable resources.
    $publishes = ServiceProvider::pathsToPublish(
        DeckServiceProvider::class,
        'deck-config'
    );

    expect($publishes)->not->toBeEmpty();
});

test('every publishable source path exists on disk', function () {
    // A registered path that does not exist publishes nothing while still
    // reporting success — the failure mode is completely silent, so assert
    // that every source we advertise is really there. Directory casing
    // mistakes only surface on case-sensitive filesystems.
    $publishes = ServiceProvider::pathsToPublish(
        DeckServiceProvider::class
    );

    expect($publishes)->not->toBeEmpty();

    $missing = array_values(array_filter(
        array_keys($publishes),
        fn (string $source) => ! file_exists($source),
    ));

    expect($missing)->toBe([]);
});

test('migrations are publishable and the source directory holds the migrations', function () {
    $publishes = ServiceProvider::pathsToPublish(
        DeckServiceProvider::class,
        'deck-migrations'
    );

    expect($publishes)->not->toBeEmpty();

    $source = array_key_first($publishes);

    // Asserted by name rather than by count, so adding a migration does not
    // break the guard. What matters is that the directory really resolves and
    // really holds the table definitions.
    $migrations = array_map('basename', glob(rtrim($source, '/').'/*.php'));

    expect(is_dir($source))->toBeTrue()
        ->and($migrations)->toContain('2025_02_28_0000001_create_prompt_versions.php')
        ->and($migrations)->toContain('2025_02_28_0000001_create_prompt_executions.php');
});

test('stubs are not included in default provider publishing', function () {
    // When publishing via --provider, stubs should not be included.
    $allPublishes = ServiceProvider::pathsToPublish(
        DeckServiceProvider::class
    );

    $stubPaths = array_filter($allPublishes, fn ($path) => str_contains($path, '.stub'));

    expect($stubPaths)->toBeEmpty();
});

test('tracking defaults to off in the package config', function () {
    // Tracking needs migrations that are published in a separate, opt-in step,
    // so it must not default on: prompt rendering would depend on tables that
    // may not exist.
    //
    // Asserted against the source rather than the evaluated value, because
    // phpunit.xml pins DECK_TRACKING_ENABLED for the whole suite, which would
    // mask the default either way.
    $source = file_get_contents(__DIR__.'/../../config/deck.php');

    expect($source)->toContain("'enabled'    => env('DECK_TRACKING_ENABLED', false)")
        ->and($source)->not->toContain("env('DECK_TRACKING_ENABLED', env('APP_DEBUG'");
});
