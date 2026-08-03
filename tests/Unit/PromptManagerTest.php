<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PromptPHP\Deck\Exceptions\InvalidVersionException;
use PromptPHP\Deck\Exceptions\PromptNotFoundException;
use PromptPHP\Deck\PromptManager;
use PromptPHP\Deck\PromptTemplate;

// =====================================================================
// Helper to get a fresh PromptManager bound to the test's temp directory
// =====================================================================

function freshManager(?array $configOverrides = []): PromptManager
{
    $app = app();

    // Apply any config overrides.
    foreach ($configOverrides as $key => $value) {
        $app['config']->set($key, $value);
    }

    return new PromptManager(
        $app['config']->get('deck.path'),
        $app['config']->get('deck.extension', 'md'),
        $app['cache']->store('array'),
        $app['config'],
    );
}

// =====================================================================
// prompt() — filesystem loading
// =====================================================================

test('get() loads system and user content from filesystem', function () {
    $this->createPromptFixture('greeting', 1, 'You are helpful.', 'Hello {{ $name }}');

    $prompt = freshManager()->get('greeting', 1);

    expect($prompt)->toBeInstanceOf(PromptTemplate::class)
        ->and($prompt->name())->toBe('greeting')
        ->and($prompt->version())->toBe(1)
        ->and($prompt->system())->toBe('You are helpful.')
        ->and($prompt->user(['name' => 'World']))->toBe('Hello World');
});

test('get() dynamically loads any role file from the version directory', function () {
    $versionPath = $this->createPromptFixture('dynamic', 1, 'system text');
    file_put_contents("{$versionPath}/assistant.md", 'assistant content');
    file_put_contents("{$versionPath}/developer.md", 'developer content');

    $prompt = freshManager()->get('dynamic', 1);

    expect($prompt->has('system'))->toBeTrue()
        ->and($prompt->has('assistant'))->toBeTrue()
        ->and($prompt->has('developer'))->toBeTrue()
        ->and($prompt->assistant())->toBe('assistant content')
        ->and($prompt->developer())->toBe('developer content');
});

test('get() returns false for has() when role file is absent', function () {
    $this->createPromptFixture('no-system', 1, null, 'user text only');

    $prompt = freshManager()->get('no-system', 1);

    expect($prompt->has('system'))->toBeFalse()
        ->and($prompt->has('user'))->toBeTrue();
});

test('get() has no user role when user file is absent', function () {
    $this->createPromptFixture('no-user', 1, 'system text', null);

    $prompt = freshManager()->get('no-user', 1);

    expect($prompt->has('user'))->toBeFalse()
        ->and($prompt->user())->toBe('');
});

test('get() loads per-version metadata.json', function () {
    $meta = ['description' => 'Version 1 prompt', 'author' => 'tester'];
    $this->createPromptFixture('with-meta', 1, 'sys', 'usr', $meta);

    $prompt = freshManager()->get('with-meta', 1);

    expect($prompt->metadata())->toBe($meta);
});

test('get() returns empty metadata when metadata.json is absent', function () {
    $this->createPromptFixture('no-meta', 1, 'sys', 'usr');

    $prompt = freshManager()->get('no-meta', 1);

    expect($prompt->metadata())->toBe([]);
});

test('get() throws InvalidVersionException for non-existent version directory', function () {
    $this->createPromptFixture('exists', 1, 'sys', 'usr');

    freshManager()->get('exists', 99);
})->throws(InvalidVersionException::class, 'Version 99 for prompt [exists] does not exist.');

test('get() uses custom extension from config', function () {
    $versionPath = "{$this->tempDir}/custom-ext/v1";
    mkdir($versionPath, 0755, true);
    file_put_contents("{$versionPath}/system.txt", 'txt system');
    file_put_contents("{$versionPath}/user.txt", 'txt user');

    $this->app['config']->set('deck.extension', 'txt');
    $prompt = freshManager()->get('custom-ext', 1);

    expect($prompt->system())->toBe('txt system')
        ->and($prompt->user())->toBe('txt user');
});

// =====================================================================
// prompt() — caching
// =====================================================================

test('get() returns from cache when cache is enabled and warm', function () {
    $this->createPromptFixture('cached', 1, 'original system', 'original user');

    $this->app['config']->set('deck.cache.enabled', true);
    $this->app['config']->set('deck.cache.ttl', 3600);

    $manager = freshManager();

    // First call populates cache.
    $prompt1 = $manager->get('cached', 1);
    expect($prompt1->system())->toBe('original system');

    // Overwrite file on disk.
    file_put_contents("{$this->tempDir}/cached/v1/system.md", 'modified system');

    // Second call should still return cached version.
    $prompt2 = $manager->get('cached', 1);
    expect($prompt2->system())->toBe('original system');
});

test('get() skips cache when cache.enabled is false', function () {
    $this->createPromptFixture('uncached', 1, 'first', 'user');

    $this->app['config']->set('deck.cache.enabled', false);

    $manager = freshManager();

    $prompt1 = $manager->get('uncached', 1);
    expect($prompt1->system())->toBe('first');

    // Overwrite file on disk.
    file_put_contents("{$this->tempDir}/uncached/v1/system.md", 'second');

    // Should read from filesystem again.
    $prompt2 = $manager->get('uncached', 1);
    expect($prompt2->system())->toBe('second');
});

// =====================================================================
// active() and getActiveVersion()
// =====================================================================

test('active() returns prompt with the active version from metadata.json', function () {
    $this->createPromptFixture('multi', 1, 'sys v1', 'usr v1');
    $this->createPromptFixture('multi', 2, 'sys v2', 'usr v2');
    $this->createPromptFixture('multi', 3, 'sys v3', 'usr v3', null, ['active_version' => 2]);

    $prompt = freshManager()->active('multi');

    expect($prompt->version())->toBe(2)
        ->and($prompt->system())->toBe('sys v2');
});

test('active() falls back to highest version when no active_version is set', function () {
    $this->createPromptFixture('no-active', 1, 'sys v1', 'usr v1');
    $this->createPromptFixture('no-active', 2, 'sys v2', 'usr v2');
    $this->createPromptFixture('no-active', 3, 'sys v3', 'usr v3');

    $prompt = freshManager()->active('no-active');

    expect($prompt->version())->toBe(3);
});

test('active() throws InvalidVersionException when prompt has no version directories', function () {
    mkdir("{$this->tempDir}/empty-prompt", 0755, true);

    freshManager()->active('empty-prompt');
})->throws(InvalidVersionException::class, 'No versions found for prompt [empty-prompt].');

test('get() without version argument uses active version', function () {
    $this->createPromptFixture('auto', 1, 'sys v1', 'usr v1', null, ['active_version' => 1]);

    $prompt = freshManager()->get('auto');

    expect($prompt->version())->toBe(1);
});

// =====================================================================
// versions()
// =====================================================================

test('versions() returns sorted list of versions with metadata', function () {
    $this->createPromptFixture('versioned', 2, 'sys', 'usr', ['description' => 'v2']);
    $this->createPromptFixture('versioned', 1, 'sys', 'usr', ['description' => 'v1']);
    $this->createPromptFixture('versioned', 3, 'sys', 'usr', ['description' => 'v3']);

    $versions = freshManager()->versions('versioned');

    expect($versions)->toHaveCount(3)
        ->and($versions[0]['version'])->toBe(1)
        ->and($versions[1]['version'])->toBe(2)
        ->and($versions[2]['version'])->toBe(3)
        ->and($versions[0]['metadata']['description'])->toBe('v1');
});

test('versions() ignores non-version directories', function () {
    $this->createPromptFixture('mixed-dirs', 1, 'sys', 'usr');
    mkdir("{$this->tempDir}/mixed-dirs/drafts", 0755, true);
    mkdir("{$this->tempDir}/mixed-dirs/notes", 0755, true);

    $versions = freshManager()->versions('mixed-dirs');

    expect($versions)->toHaveCount(1)
        ->and($versions[0]['version'])->toBe(1);
});

test('versions() throws PromptNotFoundException when directory does not exist', function () {
    freshManager()->versions('nonexistent');
})->throws(PromptNotFoundException::class, 'Prompt [nonexistent] not found.');

test('versions() returns empty array when directory exists but has no v* subdirs', function () {
    mkdir("{$this->tempDir}/empty-versions", 0755, true);

    $versions = freshManager()->versions('empty-versions');

    expect($versions)->toBe([]);
});

test('versions() includes version metadata from per-version metadata.json', function () {
    $this->createPromptFixture('meta-versions', 1, 'sys', 'usr', ['author' => 'Alice']);
    $this->createPromptFixture('meta-versions', 2, 'sys', 'usr');

    $versions = freshManager()->versions('meta-versions');

    expect($versions[0]['metadata']['author'])->toBe('Alice')
        ->and($versions[1]['metadata'])->toBe([]);
});

// =====================================================================
// activate() — filesystem mode (tracking disabled)
// =====================================================================

test('activate() writes active_version to metadata.json', function () {
    $this->createPromptFixture('activatable', 1, 'sys', 'usr');
    $this->createPromptFixture('activatable', 2, 'sys', 'usr');

    $result = freshManager()->activate('activatable', 2);

    expect($result)->toBeTrue();

    $metadata = json_decode(file_get_contents("{$this->tempDir}/activatable/metadata.json"), true);
    expect($metadata['active_version'])->toBe(2);
});

test('activate() creates metadata.json if it does not exist', function () {
    $this->createPromptFixture('no-meta-activate', 1, 'sys', 'usr');

    $metaPath = "{$this->tempDir}/no-meta-activate/metadata.json";
    expect(file_exists($metaPath))->toBeFalse();

    freshManager()->activate('no-meta-activate', 1);

    expect(file_exists($metaPath))->toBeTrue();
    $meta = json_decode(file_get_contents($metaPath), true);
    expect($meta['active_version'])->toBe(1);
});

test('activate() preserves existing metadata keys when updating active_version', function () {
    $this->createPromptFixture('preserve-meta', 1, 'sys', 'usr', null, [
        'name'           => 'preserve-meta',
        'description'    => 'My prompt',
        'active_version' => 1,
    ]);

    // Version 2 must actually exist before it can be activated.
    mkdir("{$this->tempDir}/preserve-meta/v2", 0777, true);

    freshManager()->activate('preserve-meta', 2);

    $meta = json_decode(file_get_contents("{$this->tempDir}/preserve-meta/metadata.json"), true);

    expect($meta['active_version'])->toBe(2)
        ->and($meta['name'])->toBe('preserve-meta')
        ->and($meta['description'])->toBe('My prompt');
});

test('activate() returns true in filesystem mode when version exists', function () {
    $this->createPromptFixture('fs-activate', 1, 'sys', 'usr');

    $result = freshManager()->activate('fs-activate', 1);

    expect($result)->toBeTrue();
});

test('activate() throws in filesystem mode when version does not exist', function () {
    $this->createPromptFixture('fs-activate-missing', 1, 'sys', 'usr');

    freshManager()->activate('fs-activate-missing', 999);
})->throws(
    InvalidArgumentException::class,
    'Version [999] does not exist for prompt [fs-activate-missing].'
);

// =====================================================================
// activate() — database mode (tracking enabled)
// =====================================================================

test('activate() with tracking enabled updates database', function () {
    $this->setUpTrackingTables();
    $this->createPromptFixture('db-activate', 1, 'sys', 'usr');
    $this->createPromptFixture('db-activate', 2, 'sys', 'usr');

    $this->app['config']->set('deck.tracking.enabled', true);
    $this->app['config']->set('deck.tracking.connection', 'testing');

    DB::connection('testing')->table('prompt_versions')->insert([
        ['name' => 'db-activate', 'version' => 1, 'is_active' => true],
        ['name' => 'db-activate', 'version' => 2, 'is_active' => false],
    ]);

    $result = freshManager()->activate('db-activate', 2);

    expect($result)->toBeTrue();

    $v1 = DB::connection('testing')->table('prompt_versions')
        ->where('name', 'db-activate')->where('version', 1)->first();
    $v2 = DB::connection('testing')->table('prompt_versions')
        ->where('name', 'db-activate')->where('version', 2)->first();

    expect((bool) $v1->is_active)->toBeFalse()
        ->and((bool) $v2->is_active)->toBeTrue();
});

test('activate() with tracking enabled throws when prompt does not exist', function () {
    $this->setUpTrackingTables();

    $this->app['config']->set('deck.tracking.enabled', true);
    $this->app['config']->set('deck.tracking.connection', 'testing');

    freshManager()->activate('nonexistent', 99);
})->throws(
    InvalidArgumentException::class,
    'Prompt [nonexistent] does not exist.'
);

// =====================================================================
// getActiveVersion() — database mode
// =====================================================================

test('getActiveVersion reads from database when tracking is enabled', function () {
    $this->setUpTrackingTables();
    $this->createPromptFixture('db-active', 1, 'sys v1', 'usr v1');
    $this->createPromptFixture('db-active', 2, 'sys v2', 'usr v2');

    $this->app['config']->set('deck.tracking.enabled', true);
    $this->app['config']->set('deck.tracking.connection', 'testing');

    DB::connection('testing')->table('prompt_versions')->insert([
        ['name' => 'db-active', 'version' => 1, 'is_active' => false],
        ['name' => 'db-active', 'version' => 2, 'is_active' => true],
    ]);

    $prompt = freshManager()->active('db-active');

    expect($prompt->version())->toBe(2);
});

test('getActiveVersion falls back to metadata.json when DB has no active record', function () {
    $this->setUpTrackingTables();
    $this->createPromptFixture('db-fallback', 1, 'sys v1', 'usr v1');
    $this->createPromptFixture('db-fallback', 2, 'sys v2', 'usr v2', null, ['active_version' => 1]);

    $this->app['config']->set('deck.tracking.enabled', true);
    $this->app['config']->set('deck.tracking.connection', 'testing');

    // No active record in DB.
    DB::connection('testing')->table('prompt_versions')->insert([
        ['name' => 'db-fallback', 'version' => 1, 'is_active' => false],
        ['name' => 'db-fallback', 'version' => 2, 'is_active' => false],
    ]);

    $prompt = freshManager()->active('db-fallback');

    expect($prompt->version())->toBe(1);
});

// =====================================================================
// track()
// =====================================================================

test('track() inserts execution record with all fields when tracking enabled', function () {
    $this->setUpTrackingTables();

    $this->app['config']->set('deck.tracking.enabled', true);
    $this->app['config']->set('deck.tracking.connection', 'testing');

    $manager = freshManager();
    $manager->track('greeting', 1, [
        'input'    => ['message' => 'hello'],
        'output'   => 'Hi there!',
        'tokens'   => 150,
        'latency'  => 234.5,
        'cost'     => 0.002,
        'model'    => 'gpt-4',
        'provider' => 'openai',
        'feedback' => ['rating' => 5],
    ]);

    $record = DB::connection('testing')->table('prompt_executions')->first();

    expect($record)->not->toBeNull()
        ->and($record->prompt_name)->toBe('greeting')
        ->and($record->prompt_version)->toBe(1)
        ->and($record->output)->toBe('Hi there!')
        ->and($record->tokens)->toBe(150)
        ->and($record->model)->toBe('gpt-4')
        ->and($record->provider)->toBe('openai');
});

test('track() handles partial data with null fields', function () {
    $this->setUpTrackingTables();

    $this->app['config']->set('deck.tracking.enabled', true);
    $this->app['config']->set('deck.tracking.connection', 'testing');

    $manager = freshManager();
    $manager->track('minimal', 2, [
        'input'  => 'simple input',
        'output' => 'simple output',
    ]);

    $record = DB::connection('testing')->table('prompt_executions')->first();

    expect($record)->not->toBeNull()
        ->and($record->prompt_name)->toBe('minimal')
        ->and($record->tokens)->toBeNull()
        ->and($record->model)->toBeNull()
        ->and($record->cost)->toBeNull();
});

test('track() does nothing when tracking is disabled', function () {
    $this->setUpTrackingTables();

    $this->app['config']->set('deck.tracking.enabled', false);

    $manager = freshManager();
    $manager->track('ignored', 1, ['input' => 'test', 'output' => 'response']);

    $count = DB::connection('testing')->table('prompt_executions')->count();

    expect($count)->toBe(0);
});

// =====================================================================
// Constructor edge cases
// =====================================================================

test('constructor trims trailing slashes from basePath', function () {
    $this->createPromptFixture('trimmed', 1, 'sys', 'usr');

    // Explicitly pass path with trailing slash.
    $manager = new PromptManager(
        $this->tempDir.'/',
        'md',
        app('cache')->store('array'),
        app('config'),
    );

    $prompt = $manager->get('trimmed', 1);

    expect($prompt->system())->toBe('sys');
});

test('constructor strips leading dot from extension', function () {
    $versionPath = "{$this->tempDir}/dot-ext/v1";
    mkdir($versionPath, 0755, true);
    file_put_contents("{$versionPath}/system.txt", 'dotted');
    file_put_contents("{$versionPath}/user.txt", 'user dotted');

    $manager = new PromptManager(
        $this->tempDir,
        '.txt',
        app('cache')->store('array'),
        app('config'),
    );

    $prompt = $manager->get('dot-ext', 1);

    expect($prompt->system())->toBe('dotted');
});

// =====================================================================
// Metadata merging — prompt-level + version-level
// =====================================================================

test('get() merges prompt-level metadata into the version metadata', function () {
    $this->createPromptFixture(
        'merged-meta',
        1,
        'sys',
        'usr',
        ['author'      => 'Alice'],
        ['description' => 'Summarises an order', 'active_version' => 1],
    );

    $metadata = freshManager()->get('merged-meta', 1)->metadata();

    expect($metadata['description'])->toBe('Summarises an order')
        ->and($metadata['author'])->toBe('Alice');
});

test('get() lets version metadata win over prompt-level metadata', function () {
    $this->createPromptFixture(
        'meta-precedence',
        1,
        'sys',
        'usr',
        ['description' => 'Version specific'],
        ['description' => 'Prompt wide'],
    );

    expect(freshManager()->get('meta-precedence', 1)->metadata()['description'])
        ->toBe('Version specific');
});

test('get() excludes active_version from metadata', function () {
    $this->createPromptFixture(
        'no-active-key',
        1,
        'sys',
        'usr',
        null,
        ['description' => 'Has an active version', 'active_version' => 1],
    );

    $metadata = freshManager()->get('no-active-key', 1)->metadata();

    expect($metadata)->not->toHaveKey('active_version')
        ->and($metadata['description'])->toBe('Has an active version');
});

test('get() still returns empty metadata when neither metadata file exists', function () {
    $this->createPromptFixture('truly-no-meta', 1, 'sys', 'usr');

    expect(freshManager()->get('truly-no-meta', 1)->metadata())->toBe([]);
});

test('get() ignores malformed metadata JSON instead of failing', function () {
    $this->createPromptFixture('bad-json', 1, 'sys', 'usr');
    file_put_contents("{$this->tempDir}/bad-json/metadata.json", '{not valid json');

    expect(freshManager()->get('bad-json', 1)->metadata())->toBe([]);
});

test('versions() merges prompt-level metadata into every version', function () {
    $this->createPromptFixture('shared-desc', 1, 'sys', 'usr', ['author' => 'Alice']);
    $this->createPromptFixture('shared-desc', 2, 'sys', 'usr', null, ['description' => 'Shared']);

    $versions = freshManager()->versions('shared-desc');

    expect($versions[0]['metadata']['description'])->toBe('Shared')
        ->and($versions[0]['metadata']['author'])->toBe('Alice')
        ->and($versions[1]['metadata']['description'])->toBe('Shared');
});

// =====================================================================
// Version input formats — get() and activate() must agree
// =====================================================================

test('activate() accepts a v-prefixed version string', function () {
    $this->createPromptFixture('str-activate', 1, 'sys');
    $this->createPromptFixture('str-activate', 2, 'sys');

    expect(freshManager()->activate('str-activate', 'v2'))->toBeTrue();

    $meta = json_decode(file_get_contents("{$this->tempDir}/str-activate/metadata.json"), true);
    expect($meta['active_version'])->toBe(2);
});

test('activate() accepts a numeric version string', function () {
    $this->createPromptFixture('num-str-activate', 1, 'sys');
    $this->createPromptFixture('num-str-activate', 2, 'sys');

    expect(freshManager()->activate('num-str-activate', '2'))->toBeTrue();

    $meta = json_decode(file_get_contents("{$this->tempDir}/num-str-activate/metadata.json"), true);
    expect($meta['active_version'])->toBe(2);
});

test('activate() still accepts an integer version', function () {
    $this->createPromptFixture('int-activate', 1, 'sys');
    $this->createPromptFixture('int-activate', 2, 'sys');

    expect(freshManager()->activate('int-activate', 2))->toBeTrue();

    $meta = json_decode(file_get_contents("{$this->tempDir}/int-activate/metadata.json"), true);
    expect($meta['active_version'])->toBe(2);
});

test('activate() reports the offending value for an unparseable version', function () {
    $this->createPromptFixture('bad-activate', 1, 'sys');

    freshManager()->activate('bad-activate', 'banana');
})->throws(
    InvalidVersionException::class,
    'Invalid version [banana] for prompt [bad-activate]. Use a positive number like [1] or [v1].'
);

test('activate() rejects a zero version', function () {
    $this->createPromptFixture('zero-activate', 1, 'sys');

    freshManager()->activate('zero-activate', 'v0');
})->throws(InvalidVersionException::class, 'Invalid version [v0]');

test('get() reports the offending value for an unparseable version', function () {
    $this->createPromptFixture('bad-get', 1, 'sys');

    freshManager()->get('bad-get', 'banana');
})->throws(
    InvalidVersionException::class,
    'Invalid version [banana] for prompt [bad-get]. Use a positive number like [1] or [v1].'
);

test('get() and activate() accept the same version formats', function () {
    $this->createPromptFixture('parity', 1, 'sys v1');
    $this->createPromptFixture('parity', 2, 'sys v2');

    $manager = freshManager();

    foreach (['v2', '2', 2] as $input) {
        expect($manager->get('parity', $input)->version())->toBe(2)
            ->and($manager->activate('parity', $input))->toBeTrue();
    }
});
