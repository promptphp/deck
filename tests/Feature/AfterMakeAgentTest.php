<?php

declare(strict_types=1);

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use PromptPHP\Deck\Listeners\AfterMakeAgent;

// =====================================================================
// Listener instantiation
// =====================================================================

test('AfterMakeAgent can be instantiated', function () {
    expect(new AfterMakeAgent)->toBeInstanceOf(AfterMakeAgent::class);
});

// =====================================================================
// Ignores non-make:agent commands
// =====================================================================

test('handle() ignores commands that are not make:agent', function () {
    $input  = new ArrayInput(['name' => 'SalesCoach']);
    $output = new BufferedOutput;

    $event    = new CommandFinished('make:model', $input, $output, 0);
    $listener = new AfterMakeAgent;

    // Should not call make:prompt — no error, just a no-op.
    $listener->handle($event);

    expect($output->fetch())->not->toContain('PromptDeck');
});

test('handle() ignores make:agent when exit code is non-zero', function () {
    $input  = new ArrayInput(['name' => 'SalesCoach']);
    $output = new BufferedOutput;

    $event    = new CommandFinished('make:agent', $input, $output, 1);
    $listener = new AfterMakeAgent;

    $listener->handle($event);

    expect($output->fetch())->not->toContain('PromptDeck');
});

// =====================================================================
// Resolves agent name correctly
// =====================================================================

test('handle() extracts agent name from input and calls make:prompt', function () {
    $basePath = config('prompt-deck.path');

    // Ensure clean state.
    $promptDir = "{$basePath}/sales-coach";

    if (is_dir($promptDir)) {
        (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($promptDir);
    }

    $input  = new ArrayInput(['name' => 'SalesCoach']);
    $output = new BufferedOutput;

    // Bind the 'name' argument explicitly since ArrayInput needs definition.
    $input = Mockery::mock(\Symfony\Component\Console\Input\InputInterface::class);
    $input->shouldReceive('getArgument')->with('name')->andReturn('SalesCoach');

    $output = new BufferedOutput;

    $event    = new CommandFinished('make:agent', $input, $output, 0);
    $listener = new AfterMakeAgent;

    $listener->handle($event);

    // Verify the prompt directory was created.
    expect(is_dir("{$basePath}/sales-coach"))->toBeTrue();

    // Verify output contains PromptDeck confirmation.
    $text = $output->fetch();
    expect($text)->toContain('PromptDeck');
    expect($text)->toContain('sales-coach');
    expect($text)->toContain('SalesCoach');

    // Cleanup.
    (new \Illuminate\Filesystem\Filesystem)->deleteDirectory("{$basePath}/sales-coach");
});

test('handle() converts PascalCase agent names to kebab-case prompts', function () {
    $basePath = config('prompt-deck.path');

    $input = Mockery::mock(\Symfony\Component\Console\Input\InputInterface::class);
    $input->shouldReceive('getArgument')->with('name')->andReturn('DocumentAnalyzer');

    $output = new BufferedOutput;

    $event    = new CommandFinished('make:agent', $input, $output, 0);
    $listener = new AfterMakeAgent;

    $listener->handle($event);

    expect(is_dir("{$basePath}/document-analyzer"))->toBeTrue();

    $text = $output->fetch();
    expect($text)->toContain('document-analyzer');

    // Cleanup.
    (new \Illuminate\Filesystem\Filesystem)->deleteDirectory("{$basePath}/document-analyzer");
});

// =====================================================================
// Edge cases
// =====================================================================

test('handle() skips when scaffold_on_make_agent config is false', function () {
    config()->set('prompt-deck.scaffold_on_make_agent', false);

    $input = Mockery::mock(\Symfony\Component\Console\Input\InputInterface::class);
    $input->shouldNotReceive('getArgument');

    $output = new BufferedOutput;

    $event    = new CommandFinished('make:agent', $input, $output, 0);
    $listener = new AfterMakeAgent;

    $listener->handle($event);

    expect($output->fetch())->not->toContain('PromptDeck');
});

test('handle() skips when input returns null for name argument', function () {
    $input = Mockery::mock(\Symfony\Component\Console\Input\InputInterface::class);
    $input->shouldReceive('getArgument')->with('name')->andReturn(null);

    $output = new BufferedOutput;

    $event    = new CommandFinished('make:agent', $input, $output, 0);
    $listener = new AfterMakeAgent;

    $listener->handle($event);

    expect($output->fetch())->not->toContain('PromptDeck');
});

test('handle() skips when agent name argument is empty', function () {
    $input = Mockery::mock(\Symfony\Component\Console\Input\InputInterface::class);
    $input->shouldReceive('getArgument')->with('name')->andReturn('');

    $output = new BufferedOutput;

    $event    = new CommandFinished('make:agent', $input, $output, 0);
    $listener = new AfterMakeAgent;

    $listener->handle($event);

    expect($output->fetch())->not->toContain('PromptDeck');
});

test('handle() skips when getArgument throws', function () {
    $input = Mockery::mock(\Symfony\Component\Console\Input\InputInterface::class);
    $input->shouldReceive('getArgument')->with('name')->andThrow(new \RuntimeException('No such argument'));

    $output = new BufferedOutput;

    $event    = new CommandFinished('make:agent', $input, $output, 0);
    $listener = new AfterMakeAgent;

    $listener->handle($event);

    expect($output->fetch())->not->toContain('PromptDeck');
});

test('handle() does not fail when prompt directory already exists', function () {
    $basePath = config('prompt-deck.path');

    // Pre-create the prompt via make:prompt.
    Artisan::call('make:prompt', ['name' => 'existing-agent']);

    $input = Mockery::mock(\Symfony\Component\Console\Input\InputInterface::class);
    $input->shouldReceive('getArgument')->with('name')->andReturn('ExistingAgent');

    $output = new BufferedOutput;

    $event    = new CommandFinished('make:agent', $input, $output, 0);
    $listener = new AfterMakeAgent;

    // Should not throw — silently skips when prompt already exists.
    $listener->handle($event);

    // Should NOT contain PromptDeck message since the prompt was skipped.
    expect($output->fetch())->not->toContain('PromptDeck');

    // Cleanup.
    (new \Illuminate\Filesystem\Filesystem)->deleteDirectory("{$basePath}/existing-agent");
});

test('handle() strips namespace prefix from agent name', function () {
    $basePath = config('prompt-deck.path');

    $input = Mockery::mock(\Symfony\Component\Console\Input\InputInterface::class);
    $input->shouldReceive('getArgument')->with('name')->andReturn('App\\Ai\\Agents\\SupportBot');

    $output = new BufferedOutput;

    $event    = new CommandFinished('make:agent', $input, $output, 0);
    $listener = new AfterMakeAgent;

    $listener->handle($event);

    // class_basename strips the namespace — should create "support-bot".
    expect(is_dir("{$basePath}/support-bot"))->toBeTrue();

    $text = $output->fetch();
    expect($text)->toContain('support-bot');

    // Cleanup.
    (new \Illuminate\Filesystem\Filesystem)->deleteDirectory("{$basePath}/support-bot");
});
