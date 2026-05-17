<?php

declare(strict_types=1);

use PromptPHP\Deck\Concerns\HasPromptTemplate;
use PromptPHP\Deck\PromptManager;
use PromptPHP\Deck\PromptTemplate;

// =====================================================================
// Helper: anonymous agent class using the trait
// =====================================================================

function makeAgent(array $overrides = []): object
{
    return new class($overrides)
    {
        use HasPromptTemplate;

        protected array $overrides;

        public function __construct(array $overrides = [])
        {
            $this->overrides = $overrides;
        }

        public function promptName(): string
        {
            return $this->overrides['name'] ?? str(class_basename($this))->kebab()->toString();
        }

        public function promptVersion(): ?int
        {
            return $this->overrides['version'] ?? null;
        }

        public function promptVariables(): array
        {
            return $this->overrides['variables'] ?? [];
        }
    };
}

// =====================================================================
// promptName()
// =====================================================================

test('promptName() defaults to kebab-cased class name', function () {
    $agent = new class
    {
        use HasPromptTemplate;
    };

    // Anonymous classes won't produce a nice name, but the method should not throw.
    expect($agent->promptName())->toBeString();
});

test('promptName() can be overridden', function () {
    $agent = makeAgent(['name' => 'sales-coach']);

    expect($agent->promptName())->toBe('sales-coach');
});

// =====================================================================
// promptVersion()
// =====================================================================

test('promptVersion() defaults to null (active version)', function () {
    $agent = new class
    {
        use HasPromptTemplate;
    };

    expect($agent->promptVersion())->toBeNull();
});

test('promptVersion() can be overridden to pin a version', function () {
    $agent = makeAgent(['version' => 3]);

    expect($agent->promptVersion())->toBe(3);
});

// =====================================================================
// promptVariables()
// =====================================================================

test('promptVariables() defaults to empty array', function () {
    $agent = new class
    {
        use HasPromptTemplate;
    };

    expect($agent->promptVariables())->toBe([]);
});

test('promptVariables() can be overridden with dynamic values', function () {
    $agent = makeAgent(['variables' => ['user_name' => 'Alice']]);

    expect($agent->promptVariables())->toBe(['user_name' => 'Alice']);
});

// =====================================================================
// promptTemplate()
// =====================================================================

test('promptTemplate() loads from PromptManager and caches', function () {
    $template = new PromptTemplate('sales-coach', 1, ['system' => 'You are a coach.']);

    $manager = Mockery::mock(PromptManager::class);
    $manager->shouldReceive('get')
        ->once() // Should only be called once due to caching.
        ->with('sales-coach', null)
        ->andReturn($template);

    app()->instance(PromptManager::class, $manager);

    $agent = makeAgent(['name' => 'sales-coach']);

    // First call loads from manager.
    $result = $agent->promptTemplate();
    expect($result)->toBe($template);

    // Second call returns cached instance (manager not called again).
    $result2 = $agent->promptTemplate();
    expect($result2)->toBe($template);
});

test('promptTemplate() passes version when specified', function () {
    $template = new PromptTemplate('sales-coach', 2, ['system' => 'V2 instructions.']);

    $manager = Mockery::mock(PromptManager::class);
    $manager->shouldReceive('get')
        ->once()
        ->with('sales-coach', 2)
        ->andReturn($template);

    app()->instance(PromptManager::class, $manager);

    $agent = makeAgent(['name' => 'sales-coach', 'version' => 2]);

    expect($agent->promptTemplate()->version())->toBe(2);
});

// =====================================================================
// instructions()
// =====================================================================

test('instructions() returns the system role content', function () {
    $template = new PromptTemplate('coach', 1, [
        'system' => 'You are a sales coach.',
        'user'   => 'Analyse this transcript.',
    ]);

    $manager = Mockery::mock(PromptManager::class);
    $manager->shouldReceive('get')->once()->andReturn($template);
    app()->instance(PromptManager::class, $manager);

    $agent = makeAgent(['name' => 'coach']);

    expect($agent->instructions())->toBe('You are a sales coach.');
});

test('instructions() interpolates variables', function () {
    $template = new PromptTemplate('coach', 1, [
        'system' => 'You are coaching {{ $user_name }}.',
    ]);

    $manager = Mockery::mock(PromptManager::class);
    $manager->shouldReceive('get')->once()->andReturn($template);
    app()->instance(PromptManager::class, $manager);

    $agent = makeAgent([
        'name'      => 'coach',
        'variables' => ['user_name' => 'Alice'],
    ]);

    expect($agent->instructions())->toBe('You are coaching Alice.');
});

test('instructions() returns empty string when system role is missing', function () {
    $template = new PromptTemplate('coach', 1, [
        'user' => 'Just a user prompt.',
    ]);

    $manager = Mockery::mock(PromptManager::class);
    $manager->shouldReceive('get')->once()->andReturn($template);
    app()->instance(PromptManager::class, $manager);

    $agent = makeAgent(['name' => 'coach']);

    expect($agent->instructions())->toBe('');
});

// =====================================================================
// promptMessages()
// =====================================================================

test('promptMessages() excludes system role by default', function () {
    $template = new PromptTemplate('coach', 1, [
        'system'    => 'System prompt.',
        'user'      => 'User prompt.',
        'assistant' => 'Assistant prompt.',
    ]);

    $manager = Mockery::mock(PromptManager::class);
    $manager->shouldReceive('get')->once()->andReturn($template);
    app()->instance(PromptManager::class, $manager);

    $agent    = makeAgent(['name' => 'coach']);
    $messages = $agent->promptMessages();

    // Should have user and assistant, but NOT system.
    expect($messages)->toHaveCount(2);
    expect($messages[0]['role'])->toBe('user');
    expect($messages[0]['content'])->toBe('User prompt.');
    expect($messages[1]['role'])->toBe('assistant');
    expect($messages[1]['content'])->toBe('Assistant prompt.');
});

test('promptMessages() can be limited to specific roles', function () {
    $template = new PromptTemplate('coach', 1, [
        'system'    => 'System prompt.',
        'user'      => 'User prompt.',
        'assistant' => 'Assistant prompt.',
    ]);

    $manager = Mockery::mock(PromptManager::class);
    $manager->shouldReceive('get')->once()->andReturn($template);
    app()->instance(PromptManager::class, $manager);

    $agent    = makeAgent(['name' => 'coach']);
    $messages = $agent->promptMessages(['user']);

    expect($messages)->toHaveCount(1);
    expect($messages[0]['role'])->toBe('user');
});

test('promptMessages() interpolates variables', function () {
    $template = new PromptTemplate('coach', 1, [
        'system' => 'System for {{ $name }}.',
        'user'   => 'Hello {{ $name }}.',
    ]);

    $manager = Mockery::mock(PromptManager::class);
    $manager->shouldReceive('get')->once()->andReturn($template);
    app()->instance(PromptManager::class, $manager);

    $agent    = makeAgent(['name' => 'coach', 'variables' => ['name' => 'Bob']]);
    $messages = $agent->promptMessages();

    expect($messages)->toHaveCount(1);
    expect($messages[0]['content'])->toBe('Hello Bob.');
});

test('promptMessages() returns raw arrays when AI SDK is not installed', function () {
    $template = new PromptTemplate('coach', 1, [
        'user' => 'Hello.',
    ]);

    $manager = Mockery::mock(PromptManager::class);
    $manager->shouldReceive('get')->once()->andReturn($template);
    app()->instance(PromptManager::class, $manager);

    $agent    = makeAgent(['name' => 'coach']);
    $messages = $agent->promptMessages();

    // Without laravel/ai installed, should return plain arrays.
    expect($messages[0])->toBeArray();
    expect($messages[0])->toHaveKeys(['role', 'content']);
});

test('promptMessages() returns empty array when no non-system roles exist', function () {
    $template = new PromptTemplate('coach', 1, [
        'system' => 'Only system.',
    ]);

    $manager = Mockery::mock(PromptManager::class);
    $manager->shouldReceive('get')->once()->andReturn($template);
    app()->instance(PromptManager::class, $manager);

    $agent    = makeAgent(['name' => 'coach']);
    $messages = $agent->promptMessages();

    expect($messages)->toBe([]);
});

// =====================================================================
// forgetPromptTemplate()
// =====================================================================

test('forgetPromptTemplate() clears the cached template', function () {
    $template1 = new PromptTemplate('coach', 1, ['system' => 'V1']);
    $template2 = new PromptTemplate('coach', 2, ['system' => 'V2']);

    $manager = Mockery::mock(PromptManager::class);
    $manager->shouldReceive('get')
        ->twice()
        ->andReturn($template1, $template2);

    app()->instance(PromptManager::class, $manager);

    $agent = makeAgent(['name' => 'coach']);

    expect($agent->promptTemplate()->version())->toBe(1);

    $agent->forgetPromptTemplate();

    expect($agent->promptTemplate()->version())->toBe(2);
});

test('forgetPromptTemplate() returns the agent for chaining', function () {
    $template = new PromptTemplate('coach', 1, ['system' => 'text']);

    $manager = Mockery::mock(PromptManager::class);
    $manager->shouldReceive('get')->andReturn($template);
    app()->instance(PromptManager::class, $manager);

    $agent = makeAgent(['name' => 'coach']);

    $result = $agent->forgetPromptTemplate();

    expect($result)->toBe($agent);
});

// =====================================================================
// Integration: instructions + messages work together
// =====================================================================

test('instructions() and promptMessages() provide complementary data', function () {
    $template = new PromptTemplate('coach', 1, [
        'system'    => 'You are an expert coach.',
        'user'      => 'Analyse the transcript.',
        'assistant' => 'I will analyse step by step.',
    ]);

    $manager = Mockery::mock(PromptManager::class);
    $manager->shouldReceive('get')->once()->andReturn($template);
    app()->instance(PromptManager::class, $manager);

    $agent = makeAgent(['name' => 'coach']);

    // instructions() gives the system prompt.
    expect($agent->instructions())->toBe('You are an expert coach.');

    // promptMessages() gives the conversation context (no system).
    $messages = $agent->promptMessages();
    expect($messages)->toHaveCount(2);
    expect(array_column($messages, 'role'))->not->toContain('system');
});

test('promptMessages() can include system role when explicitly requested', function () {
    $template = new PromptTemplate('coach', 1, [
        'system' => 'System.',
        'user'   => 'User.',
    ]);

    $manager = Mockery::mock(PromptManager::class);
    $manager->shouldReceive('get')->once()->andReturn($template);
    app()->instance(PromptManager::class, $manager);

    $agent    = makeAgent(['name' => 'coach']);
    $messages = $agent->promptMessages(['system', 'user']);

    expect($messages)->toHaveCount(2);
    expect($messages[0]['role'])->toBe('system');
});
