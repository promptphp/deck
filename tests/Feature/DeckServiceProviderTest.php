<?php

declare(strict_types=1);

use PromptPHP\Deck\PromptManager;

test('PromptManager is registered as a singleton', function () {
    $instance1 = $this->app->make(PromptManager::class);
    $instance2 = $this->app->make(PromptManager::class);

    expect($instance1)->toBe($instance2);
});

test('PromptManager is resolvable via prompt-deck alias', function () {
    $instance = $this->app->make('prompt-deck');

    expect($instance)->toBeInstanceOf(PromptManager::class);
});

test('alias resolves to same singleton as class binding', function () {
    $byAlias = $this->app->make('prompt-deck');
    $byClass = $this->app->make(PromptManager::class);

    expect($byAlias)->toBe($byClass);
});

test('config is merged from package config file', function () {
    // The provider merges config/prompt-deck.php.
    // Our TestCase overrides some values but the merge should have happened.
    expect(config('prompt-deck.versioning'))->toBe('directory')
        ->and(config('prompt-deck.cache.ttl'))->toBe(3600);
});

test('Artisan commands are registered', function () {
    $commands = \Illuminate\Support\Facades\Artisan::all();

    expect($commands)->toHaveKey('make:prompt')
        ->and($commands)->toHaveKey('prompt:list')
        ->and($commands)->toHaveKey('prompt:activate')
        ->and($commands)->toHaveKey('prompt:diff')
        ->and($commands)->toHaveKey('prompt:test');
});

test('publishable config is registered', function () {
    // Verify the provider has registered publishable resources.
    $publishes = \Illuminate\Support\ServiceProvider::pathsToPublish(
        \PromptPHP\Deck\Providers\DeckServiceProvider::class,
        'prompt-deck-config'
    );

    expect($publishes)->not->toBeEmpty();
});

test('stubs are not included in default provider publishing', function () {
    // When publishing via --provider, stubs should not be included.
    $allPublishes = \Illuminate\Support\ServiceProvider::pathsToPublish(
        \PromptPHP\Deck\Providers\DeckServiceProvider::class
    );

    $stubPaths = array_filter($allPublishes, fn ($path) => str_contains($path, '.stub'));

    expect($stubPaths)->toBeEmpty();
});
