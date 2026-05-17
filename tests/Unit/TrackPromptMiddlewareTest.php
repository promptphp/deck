<?php

declare(strict_types=1);

use PromptPHP\Deck\Ai\TrackPromptMiddleware;
use PromptPHP\Deck\PromptManager;
use PromptPHP\Deck\PromptTemplate;

// =====================================================================
// Middleware instantiation
// =====================================================================

test('TrackPromptMiddleware can be instantiated', function () {
    $middleware = new TrackPromptMiddleware;

    expect($middleware)->toBeInstanceOf(TrackPromptMiddleware::class);
});

// =====================================================================
// handle() passes through to next middleware
// =====================================================================

test('handle() passes the prompt through to the next middleware', function () {
    $middleware = new TrackPromptMiddleware;

    $prompt   = new stdClass;
    $response = new stdClass;

    $next = fn ($p) => $response;

    $result = $middleware->handle($prompt, $next);

    expect($result)->toBe($response);
});

test('handle() returns response from next when then() is not available', function () {
    $middleware = new TrackPromptMiddleware;

    $prompt   = new stdClass;
    $response = 'plain-response';

    $next = fn ($p) => $response;

    $result = $middleware->handle($prompt, $next);

    expect($result)->toBe('plain-response');
});

// =====================================================================
// handle() with then() callback
// =====================================================================

test('handle() calls then() when available on response', function () {
    $middleware = new TrackPromptMiddleware;

    $prompt        = new stdClass;
    $prompt->agent = null; // No agent: tracking should be safe/no-op.

    $thenWasCalled = false;

    $response = new class($thenWasCalled)
    {
        public bool $called;

        public function __construct(bool &$called)
        {
            $this->called = &$called;
        }

        public function then(Closure $callback): static
        {
            $this->called = true;
            $callback(new stdClass);

            return $this;
        }
    };

    $next = fn ($p) => $response;

    $middleware->handle($prompt, $next);

    expect($response->called)->toBeTrue();
});

// =====================================================================
// Tracking integration
// =====================================================================

test('handle() tracks execution when agent uses HasPromptTemplate', function () {
    $template = new PromptTemplate('sales-coach', 1, ['system' => 'Coach instructions.']);

    // Create an agent stub that mimics HasPromptTemplate.
    $agent = new class($template)
    {
        private PromptTemplate $tpl;

        public function __construct(PromptTemplate $tpl)
        {
            $this->tpl = $tpl;
        }

        public function promptName(): string
        {
            return 'sales-coach';
        }

        public function promptTemplate(): PromptTemplate
        {
            return $this->tpl;
        }
    };

    // Build fake prompt & response objects.
    $prompt         = new stdClass;
    $prompt->agent  = $agent;
    $prompt->prompt = 'Analyse the transcript.';

    $agentResponse        = new stdClass;
    $agentResponse->text  = 'Here is my analysis.';
    $agentResponse->model = 'gpt-4o';
    $agentResponse->usage = (object) ['totalTokens' => 150];

    // Mock PromptManager to assert track() is called.
    $manager = Mockery::mock(PromptManager::class);
    $manager->shouldReceive('track')
        ->once()
        ->with(
            'sales-coach',
            1,
            Mockery::on(function ($data) {
                return $data['input'] === 'Analyse the transcript.'
                    && $data['output'] === 'Here is my analysis.'
                    && $data['tokens'] === 150
                    && $data['model'] === 'gpt-4o'
                    && is_float($data['latency']);
            })
        );
    app()->instance(PromptManager::class, $manager);

    // Create response with then().
    $response = new class($agentResponse)
    {
        private $agentResponse;

        private ?Closure $thenCallback = null;

        public function __construct($agentResponse)
        {
            $this->agentResponse = $agentResponse;
        }

        public function then(Closure $callback): static
        {
            $callback($this->agentResponse);

            return $this;
        }
    };

    $middleware = new TrackPromptMiddleware;

    $middleware->handle($prompt, fn ($p) => $response);
});

test('handle() skips tracking when agent is null', function () {
    $manager = Mockery::mock(PromptManager::class);
    $manager->shouldNotReceive('track');
    app()->instance(PromptManager::class, $manager);

    $prompt        = new stdClass;
    $prompt->agent = null;

    $agentResponse = new stdClass;

    $response = new class($agentResponse)
    {
        private $agentResponse;

        public function __construct($agentResponse)
        {
            $this->agentResponse = $agentResponse;
        }

        public function then(Closure $callback): static
        {
            $callback($this->agentResponse);

            return $this;
        }
    };

    $middleware = new TrackPromptMiddleware;

    $middleware->handle($prompt, fn ($p) => $response);
});

test('handle() skips tracking when agent does not have promptName method', function () {
    $manager = Mockery::mock(PromptManager::class);
    $manager->shouldNotReceive('track');
    app()->instance(PromptManager::class, $manager);

    $prompt        = new stdClass;
    $prompt->agent = new stdClass; // No promptName() method.

    $agentResponse = new stdClass;

    $response = new class($agentResponse)
    {
        private $agentResponse;

        public function __construct($agentResponse)
        {
            $this->agentResponse = $agentResponse;
        }

        public function then(Closure $callback): static
        {
            $callback($this->agentResponse);

            return $this;
        }
    };

    $middleware = new TrackPromptMiddleware;

    $middleware->handle($prompt, fn ($p) => $response);
});
