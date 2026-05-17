<?php

declare(strict_types=1);

use PromptPHP\Deck\PromptTemplate;

// =====================================================================
// Accessor methods
// =====================================================================

test('name() returns the constructor name', function () {
    $prompt = new PromptTemplate('greeting', 1, ['system' => 'text']);

    expect($prompt->name())->toBe('greeting');
});

test('version() returns the constructor version', function () {
    $prompt = new PromptTemplate('greeting', 3, ['system' => 'text']);

    expect($prompt->version())->toBe(3);
});

test('metadata() returns the constructor metadata', function () {
    $meta   = ['description' => 'A test prompt', 'author' => 'tester'];
    $prompt = new PromptTemplate('greeting', 1, ['system' => 'sys'], $meta);

    expect($prompt->metadata())->toBe($meta);
});

test('metadata() defaults to empty array when not provided', function () {
    $prompt = new PromptTemplate('greeting', 1, ['system' => 'sys']);

    expect($prompt->metadata())->toBe([]);
});

// =====================================================================
// toArray
// =====================================================================

test('toArray() returns name, version, roles, and metadata', function () {
    $meta   = ['description' => 'test'];
    $roles  = ['system' => 'system content', 'user' => 'user content'];
    $prompt = new PromptTemplate('chat', 2, $roles, $meta);

    expect($prompt->toArray())->toBe([
        'name'     => 'chat',
        'version'  => 2,
        'roles'    => $roles,
        'metadata' => $meta,
    ]);
});

test('toArray() round-trips correctly via constructor', function () {
    $roles  = ['system' => 'sys prompt', 'user' => 'usr prompt'];
    $prompt = new PromptTemplate('roundtrip', 5, $roles, ['key' => 'val']);
    $array  = $prompt->toArray();

    $reconstructed = new PromptTemplate(
        $array['name'],
        $array['version'],
        $array['roles'],
        $array['metadata'],
    );

    expect($reconstructed->toArray())->toBe($prompt->toArray());
});

// =====================================================================
// role() — explicit rendering
// =====================================================================

test('role() with no variables returns raw content', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => 'Hello {{ $name }}']);

    expect($prompt->role('system'))->toBe('Hello {{ $name }}');
});

test('role() replaces {{ $var }} spaced syntax', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => 'Hello {{ $name }}, welcome!']);

    expect($prompt->role('system', ['name' => 'Alice']))->toBe('Hello Alice, welcome!');
});

test('role() replaces {{var}} non-spaced syntax', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => 'Hello {{name}}, welcome!']);

    expect($prompt->role('system', ['name' => 'Bob']))->toBe('Hello Bob, welcome!');
});

test('role() returns empty string for non-existent role', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => 'text']);

    expect($prompt->role('assistant'))->toBe('');
});

// =====================================================================
// __call — magic role access
// =====================================================================

test('system() renders the system role via __call', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => 'Tone: {{ $tone }}']);

    expect($prompt->system(['tone' => 'friendly']))->toBe('Tone: friendly');
});

test('user() renders the user role via __call', function () {
    $prompt = new PromptTemplate('test', 1, ['user' => 'Input: {{ $input }}']);

    expect($prompt->user(['input' => 'test data']))->toBe('Input: test data');
});

test('assistant() renders a custom role via __call', function () {
    $prompt = new PromptTemplate('test', 1, ['assistant' => 'Context: {{ $ctx }}']);

    expect($prompt->assistant(['ctx' => 'background']))->toBe('Context: background');
});

test('__call returns empty string for missing role', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => 'text']);

    expect($prompt->developer())->toBe('');
});

test('__call with no arguments returns raw content', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => 'Hello world']);

    expect($prompt->system())->toBe('Hello world');
});

// =====================================================================
// raw()
// =====================================================================

test('raw() returns content without interpolation', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => 'Hello {{ $name }}']);

    expect($prompt->raw('system'))->toBe('Hello {{ $name }}');
});

test('raw() returns empty string for missing role', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => 'text']);

    expect($prompt->raw('user'))->toBe('');
});

// =====================================================================
// has()
// =====================================================================

test('has() returns true for existing role', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => 'text', 'user' => 'text']);

    expect($prompt->has('system'))->toBeTrue()
        ->and($prompt->has('user'))->toBeTrue();
});

test('has() returns false for missing role', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => 'text']);

    expect($prompt->has('assistant'))->toBeFalse();
});

// =====================================================================
// roles()
// =====================================================================

test('roles() returns all role names', function () {
    $prompt = new PromptTemplate('test', 1, [
        'system'    => 'sys',
        'user'      => 'usr',
        'assistant' => 'ast',
    ]);

    expect($prompt->roles())->toBe(['system', 'user', 'assistant']);
});

test('roles() returns empty array when no roles', function () {
    $prompt = new PromptTemplate('test', 1);

    expect($prompt->roles())->toBe([]);
});

// =====================================================================
// toMessages()
// =====================================================================

test('toMessages() builds messages array for all roles', function () {
    $prompt = new PromptTemplate('test', 1, [
        'system' => 'You are {{ $tone }}',
        'user'   => 'Hello {{ $name }}',
    ]);

    $messages = $prompt->toMessages(['tone' => 'helpful', 'name' => 'Alice']);

    expect($messages)->toBe([
        ['role' => 'system', 'content' => 'You are helpful'],
        ['role' => 'user', 'content' => 'Hello Alice'],
    ]);
});

test('toMessages() respects the only parameter to filter roles', function () {
    $prompt = new PromptTemplate('test', 1, [
        'system'    => 'sys',
        'user'      => 'usr',
        'assistant' => 'ast',
    ]);

    $messages = $prompt->toMessages([], ['system', 'assistant']);

    expect($messages)->toBe([
        ['role' => 'system', 'content' => 'sys'],
        ['role' => 'assistant', 'content' => 'ast'],
    ]);
});

test('toMessages() skips roles not present in the prompt', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => 'sys']);

    $messages = $prompt->toMessages([], ['system', 'user']);

    expect($messages)->toBe([
        ['role' => 'system', 'content' => 'sys'],
    ]);
});

test('toMessages() returns empty array when prompt has no roles', function () {
    $prompt = new PromptTemplate('test', 1);

    expect($prompt->toMessages())->toBe([]);
});

// =====================================================================
// Interpolation edge cases
// =====================================================================

test('interpolation handles both syntaxes in the same string', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => '{{ $greeting }} and {{name}}!']);

    expect($prompt->system(['greeting' => 'Hi', 'name' => 'World']))
        ->toBe('Hi and World!');
});

test('interpolation leaves unreplaced placeholders intact (missing variables)', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => 'Hello {{ $name }}, you are {{ $role }}']);

    expect($prompt->system(['name' => 'Alice']))
        ->toBe('Hello Alice, you are {{ $role }}');
});

test('interpolation handles special characters in values', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => 'Content: {{ $html }}']);

    expect($prompt->system(['html' => '<script>alert("xss")</script>']))
        ->toBe('Content: <script>alert("xss")</script>');
});

test('interpolation handles dollar sign in values', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => 'Price: {{ $price }}']);

    expect($prompt->system(['price' => '$100.00']))
        ->toBe('Price: $100.00');
});

test('interpolation handles empty string value', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => 'Value: [{{ $val }}]']);

    expect($prompt->system(['val' => '']))->toBe('Value: []');
});

test('interpolation handles numeric value via string cast', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => 'Count: {{ $count }}']);

    expect($prompt->system(['count' => 42]))->toBe('Count: 42');
});

test('interpolation handles float value', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => 'Score: {{ $score }}']);

    expect($prompt->system(['score' => 3.14]))->toBe('Score: 3.14');
});

test('interpolation replaces multiple occurrences of the same variable', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => '{{ $name }} said hello to {{ $name }}']);

    expect($prompt->system(['name' => 'Alice']))
        ->toBe('Alice said hello to Alice');
});

test('interpolation handles multiple different variables', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => '{{ $a }} {{ $b }} {{ $c }}']);

    expect($prompt->system(['a' => 'X', 'b' => 'Y', 'c' => 'Z']))
        ->toBe('X Y Z');
});

test('interpolation handles multiline content', function () {
    $content = "Line 1: {{ \$name }}\nLine 2: {{ \$role }}\nLine 3: done";
    $prompt  = new PromptTemplate('test', 1, ['system' => $content]);

    expect($prompt->system(['name' => 'Alice', 'role' => 'admin']))
        ->toBe("Line 1: Alice\nLine 2: admin\nLine 3: done");
});

// =====================================================================
// Arrayable contract
// =====================================================================

test('Prompt implements Arrayable', function () {
    $prompt = new PromptTemplate('test', 1, ['system' => 'sys']);

    expect($prompt)->toBeInstanceOf(\Illuminate\Contracts\Support\Arrayable::class);
});
