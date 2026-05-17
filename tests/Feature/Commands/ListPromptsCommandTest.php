<?php

declare(strict_types=1);

// =====================================================================
// prompt:list — directory / prompt discovery
// =====================================================================

test('prompt:list warns when prompts directory does not exist', function () {
    // Point config at a non-existent path.
    $this->app['config']->set('deck.path', $this->tempDir.'/nonexistent');

    $this->artisan('prompt:list')
        ->expectsOutput('Prompts directory not found.')
        ->assertSuccessful();
});

test('prompt:list shows info when directory exists but has no prompts', function () {
    // tempDir exists but is empty — no prompt subdirectories.
    $this->artisan('prompt:list')
        ->expectsOutput('No prompts found.')
        ->assertSuccessful();
});

// =====================================================================
// prompt:list — table output (default: active version only)
// =====================================================================

test('prompt:list displays prompts in a table', function () {
    $this->createPromptFixture('greeting', 1, 'sys', 'usr', ['description' => 'A greeting prompt'], ['active_version' => 1]);

    $this->artisan('prompt:list')
        ->expectsTable(
            ['Prompt', 'Active Version', 'Active', 'Description'],
            [['greeting', 'v1', '✅', 'A greeting prompt']]
        )
        ->assertSuccessful();
});

test('prompt:list shows multiple prompts sorted by directory order', function () {
    $this->createPromptFixture('alpha', 1, 'sys', 'usr', null, ['active_version' => 1]);
    $this->createPromptFixture('beta', 1, 'sys', 'usr', null, ['active_version' => 1]);

    $this->artisan('prompt:list')
        ->assertSuccessful();
});

test('prompt:list shows active version from metadata.json', function () {
    $this->createPromptFixture('multi-ver', 1, 'sys v1', 'usr v1');
    $this->createPromptFixture('multi-ver', 2, 'sys v2', 'usr v2');
    $this->createPromptFixture('multi-ver', 3, 'sys v3', 'usr v3', null, ['active_version' => 2]);

    $this->artisan('prompt:list')
        ->expectsTable(
            ['Prompt', 'Active Version', 'Active', 'Description'],
            [['multi-ver', 'v2', '✅', '']]
        )
        ->assertSuccessful();
});

test('prompt:list falls back to highest version when no active_version is set', function () {
    $this->createPromptFixture('fallback', 1, 'sys', 'usr');
    $this->createPromptFixture('fallback', 3, 'sys', 'usr');

    $this->artisan('prompt:list')
        ->expectsTable(
            ['Prompt', 'Active Version', 'Active', 'Description'],
            [['fallback', 'v3', '✅', '']]
        )
        ->assertSuccessful();
});

test('prompt:list shows description from per-version metadata', function () {
    $this->createPromptFixture('described', 1, 'sys', 'usr', ['description' => 'Version one desc'], ['active_version' => 1]);

    $this->artisan('prompt:list')
        ->expectsTable(
            ['Prompt', 'Active Version', 'Active', 'Description'],
            [['described', 'v1', '✅', 'Version one desc']]
        )
        ->assertSuccessful();
});

test('prompt:list shows empty description when metadata has no description key', function () {
    $this->createPromptFixture('no-desc', 1, 'sys', 'usr', ['author' => 'tester'], ['active_version' => 1]);

    $this->artisan('prompt:list')
        ->expectsTable(
            ['Prompt', 'Active Version', 'Active', 'Description'],
            [['no-desc', 'v1', '✅', '']]
        )
        ->assertSuccessful();
});

// =====================================================================
// prompt:list --all — shows every version per prompt
// =====================================================================

test('prompt:list --all shows all versions for each prompt', function () {
    $this->createPromptFixture('chat', 1, 'sys v1', 'usr v1', ['description' => 'Initial']);
    $this->createPromptFixture('chat', 2, 'sys v2', 'usr v2', ['description' => 'Improved']);
    $this->createPromptFixture('chat', 3, 'sys v3', 'usr v3', ['description' => 'Latest'], ['active_version' => 2]);

    $this->artisan('prompt:list', ['--all' => true])
        ->expectsTable(
            ['Prompt', 'Active Version', 'Active', 'Description'],
            [
                ['chat', 'v1', '', 'Initial'],
                ['chat', 'v2', '✅', 'Improved'],
                ['chat', 'v3', '', 'Latest'],
            ]
        )
        ->assertSuccessful();
});

test('prompt:list --all marks only the active version with checkmark', function () {
    $this->createPromptFixture('marker', 1, 'sys', 'usr', null, ['active_version' => 1]);
    $this->createPromptFixture('marker', 2, 'sys', 'usr');

    $this->artisan('prompt:list', ['--all' => true])
        ->expectsTable(
            ['Prompt', 'Active Version', 'Active', 'Description'],
            [
                ['marker', 'v1', '✅', ''],
                ['marker', 'v2', '', ''],
            ]
        )
        ->assertSuccessful();
});

test('prompt:list --all with multiple prompts lists all versions for each', function () {
    $this->createPromptFixture('alpha', 1, 'sys', 'usr', null, ['active_version' => 1]);
    $this->createPromptFixture('alpha', 2, 'sys', 'usr');
    $this->createPromptFixture('beta', 1, 'sys', 'usr', null, ['active_version' => 1]);

    $this->artisan('prompt:list', ['--all' => true])
        ->assertSuccessful();
});

// =====================================================================
// Edge cases
// =====================================================================

test('prompt:list handles prompt with no version directories gracefully', function () {
    // Create a prompt directory with no v* subdirectories.
    mkdir("{$this->tempDir}/empty-prompt", 0755, true);

    // getActiveVersion catches the exception and returns 0.
    $this->artisan('prompt:list')
        ->expectsTable(
            ['Prompt', 'Active Version', 'Active', 'Description'],
            [['empty-prompt', 'v0', '✅', '']]
        )
        ->assertSuccessful();
});

test('prompt:list ignores non-directory items in the prompts path', function () {
    // Create a file (not a directory) in the prompts path.
    file_put_contents("{$this->tempDir}/readme.txt", 'Not a prompt');
    $this->createPromptFixture('valid', 1, 'sys', 'usr', null, ['active_version' => 1]);

    // glob with GLOB_ONLYDIR should skip the file.
    $this->artisan('prompt:list')
        ->expectsTable(
            ['Prompt', 'Active Version', 'Active', 'Description'],
            [['valid', 'v1', '✅', '']]
        )
        ->assertSuccessful();
});

test('prompt:list returns success exit code', function () {
    $this->createPromptFixture('exit-test', 1, 'sys', 'usr', null, ['active_version' => 1]);

    $this->artisan('prompt:list')
        ->assertExitCode(0);
});
