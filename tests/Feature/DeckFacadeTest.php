<?php

declare(strict_types=1);

use PromptPHP\Deck\Facades\Deck;
use PromptPHP\Deck\PromptManager;
use PromptPHP\Deck\PromptTemplate;

test('facade accessor returns prompt-deck', function () {
    // Resolve via facade accessor.
    $resolved = PromptDeck::getFacadeRoot();

    expect($resolved)->toBeInstanceOf(PromptManager::class);
});

test('PromptDeck::get() loads a prompt by name and version', function () {
    $this->createPromptFixture('facade-test', 1, 'system content', 'user content');

    $prompt = PromptDeck::get('facade-test', 1);

    expect($prompt)->toBeInstanceOf(PromptTemplate::class)
        ->and($prompt->name())->toBe('facade-test')
        ->and($prompt->version())->toBe(1)
        ->and($prompt->system())->toBe('system content')
        ->and($prompt->user())->toBe('user content');
});

test('PromptDeck::get() without version returns the active version', function () {
    $this->createPromptFixture('facade-active', 1, 'sys', 'usr');
    $this->createPromptFixture('facade-active', 2, 'sys v2', 'usr v2', null, ['active_version' => 2]);

    $prompt = PromptDeck::get('facade-active');

    expect($prompt)->toBeInstanceOf(PromptTemplate::class)
        ->and($prompt->version())->toBe(2);
});

test('PromptDeck::active() proxies to PromptManager::active()', function () {
    $this->createPromptFixture('facade-active-method', 1, 'sys', 'usr');
    $this->createPromptFixture('facade-active-method', 2, 'sys v2', 'usr v2', null, ['active_version' => 2]);

    $prompt = PromptDeck::active('facade-active-method');

    expect($prompt)->toBeInstanceOf(PromptTemplate::class)
        ->and($prompt->version())->toBe(2);
});

test('PromptDeck::versions() proxies to PromptManager::versions()', function () {
    $this->createPromptFixture('facade-versions', 1, 'sys', 'usr');
    $this->createPromptFixture('facade-versions', 2, 'sys', 'usr');

    $versions = PromptDeck::versions('facade-versions');

    expect($versions)->toHaveCount(2)
        ->and($versions[0]['version'])->toBe(1)
        ->and($versions[1]['version'])->toBe(2);
});

test('PromptDeck::activate() proxies to PromptManager::activate()', function () {
    $this->createPromptFixture('facade-activate', 1, 'sys', 'usr');
    $this->createPromptFixture('facade-activate', 2, 'sys', 'usr');

    $result = PromptDeck::activate('facade-activate', 2);

    expect($result)->toBeTrue();

    $meta = json_decode(file_get_contents("{$this->tempDir}/facade-activate/metadata.json"), true);
    expect($meta['active_version'])->toBe(2);
});
