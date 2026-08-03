<?php

declare(strict_types=1);

// =====================================================================
// prompt:test — basic rendering
// =====================================================================

test('prompt:test renders active prompt with default input', function () {
    $this->createPromptFixture(
        'test-render',
        1,
        'You are a {{ $role }} assistant.',
        'User said: {{ $input }}',
        null,
        ['active_version' => 1],
    );

    $this->artisan('prompt:test', ['name' => 'test-render'])
        ->expectsOutputToContain('Testing prompt [test-render] version 1')
        ->expectsOutputToContain('--- SYSTEM PROMPT ---')
        ->expectsOutputToContain('You are a {{ $role }} assistant.')
        ->expectsOutputToContain('--- USER PROMPT ---')
        ->expectsOutputToContain('User said: Sample user input')
        ->assertSuccessful();
});

test('prompt:test renders specific version with --ver', function () {
    $this->createPromptFixture('ver-render', 1, 'sys v1', 'usr v1: {{ $input }}');
    $this->createPromptFixture('ver-render', 2, 'sys v2', 'usr v2: {{ $input }}');

    $this->artisan('prompt:test', ['name' => 'ver-render', '--ver' => 2])
        ->expectsOutputToContain('Testing prompt [ver-render] version 2')
        ->expectsOutputToContain('sys v2')
        ->expectsOutputToContain('usr v2: Sample user input')
        ->assertSuccessful();
});

// =====================================================================
// prompt:test — custom input and variables
// =====================================================================

test('prompt:test uses --input for user prompt rendering', function () {
    $this->createPromptFixture(
        'input-test',
        1,
        'system',
        'Input: {{ $input }}',
        null,
        ['active_version' => 1],
    );

    $this->artisan('prompt:test', ['name' => 'input-test', '--input' => 'custom input text'])
        ->expectsOutputToContain('Input: custom input text')
        ->assertSuccessful();
});

test('prompt:test uses --variables JSON for rendering', function () {
    $this->createPromptFixture(
        'var-render',
        1,
        'Role: {{ $role }}',
        'Input: {{ $input }}',
        null,
        ['active_version' => 1],
    );

    $this->artisan('prompt:test', [
        'name'        => 'var-render',
        '--variables' => '{"role": "expert"}',
    ])
        ->expectsOutputToContain('Role: expert')
        ->assertSuccessful();
});

test('prompt:test merges variables with input for user prompt', function () {
    $this->createPromptFixture(
        'merge-test',
        1,
        '{{ $role }}',
        '{{ $role }}: {{ $input }}',
        null,
        ['active_version' => 1],
    );

    $this->artisan('prompt:test', [
        'name'        => 'merge-test',
        '--variables' => '{"role": "admin"}',
        '--input'     => 'hello',
    ])
        ->expectsOutputToContain('admin: hello')
        ->assertSuccessful();
});

// =====================================================================
// prompt:test — metadata variables display
// =====================================================================

test('prompt:test shows expected variables from metadata', function () {
    $this->createPromptFixture(
        'meta-render',
        1,
        'sys',
        'usr: {{ $input }}',
        ['variables'      => ['name', 'role', 'input']],
        ['active_version' => 1],
    );

    $this->artisan('prompt:test', ['name' => 'meta-render'])
        ->expectsOutputToContain('Expected variables: name, role, input')
        ->assertSuccessful();
});

// =====================================================================
// prompt:test — error handling
// =====================================================================

test('prompt:test fails with invalid JSON for --variables', function () {
    $this->artisan('prompt:test', [
        'name'        => 'any-prompt',
        '--variables' => '{invalid json}',
    ])
        ->expectsOutput('Invalid JSON for --variables')
        ->assertFailed();
});

test('prompt:test fails when prompt does not exist', function () {
    $this->artisan('prompt:test', ['name' => 'nonexistent'])
        ->assertFailed();
});

// =====================================================================
// prompt:test — version resolution
// =====================================================================

test('prompt:test renders a v-prefixed version rather than the active one', function () {
    $this->createPromptFixture('v-prefixed', 1, 'SYSTEM FROM V1', 'usr v1', null, ['active_version' => 1]);
    $this->createPromptFixture('v-prefixed', 2, 'SYSTEM FROM V2', 'usr v2');

    $this->artisan('prompt:test', ['name' => 'v-prefixed', '--ver' => 'v2'])
        ->expectsOutputToContain('Testing prompt [v-prefixed] version 2')
        ->expectsOutputToContain('SYSTEM FROM V2')
        ->doesntExpectOutputToContain('SYSTEM FROM V1')
        ->assertSuccessful();
});

test('prompt:test renders a numeric version rather than the active one', function () {
    $this->createPromptFixture('numeric-ver', 1, 'SYSTEM FROM V1', 'usr v1', null, ['active_version' => 1]);
    $this->createPromptFixture('numeric-ver', 2, 'SYSTEM FROM V2', 'usr v2');

    $this->artisan('prompt:test', ['name' => 'numeric-ver', '--ver' => '2'])
        ->expectsOutputToContain('Testing prompt [numeric-ver] version 2')
        ->expectsOutputToContain('SYSTEM FROM V2')
        ->assertSuccessful();
});

test('prompt:test fails with a clear message for an unparseable version', function () {
    $this->createPromptFixture('bad-ver', 1, 'sys', 'usr', null, ['active_version' => 1]);

    $this->artisan('prompt:test', ['name' => 'bad-ver', '--ver' => 'banana'])
        ->expectsOutput('Invalid version [banana] provided. Use a positive number like [1] or [v1].')
        ->assertFailed();
});

test('prompt:test falls back to the active version when --ver is omitted', function () {
    $this->createPromptFixture('no-ver', 1, 'SYSTEM FROM V1', 'usr v1', null, ['active_version' => 1]);
    $this->createPromptFixture('no-ver', 2, 'SYSTEM FROM V2', 'usr v2');

    $this->artisan('prompt:test', ['name' => 'no-ver'])
        ->expectsOutputToContain('Testing prompt [no-ver] version 1')
        ->expectsOutputToContain('SYSTEM FROM V1')
        ->assertSuccessful();
});
