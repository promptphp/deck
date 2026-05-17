<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Ai;

use Closure;
use PromptPHP\Deck\PromptManager;

/**
 * Laravel AI SDK agent middleware that automatically tracks
 * prompt executions through PromptDeck's tracking system.
 *
 * Add this middleware to any agent that uses HasPromptTemplate
 * to enable automatic performance tracking (tokens, latency, model, etc.).
 *
 * Usage:
 *   class SalesCoach implements Agent, HasMiddleware
 *   {
 *       use Promptable, HasPromptTemplate;
 *
 *       public function middleware(): array
 *       {
 *           return [new TrackPromptMiddleware];
 *       }
 *   }
 *
 * Requires:
 *   - laravel/ai package
 *   - PromptDeck tracking enabled in config
 *   - The agent to use the HasPromptTemplate trait
 *
 * @see https://laravel.com/docs/ai-sdk#middleware
 */
class TrackPromptMiddleware
{
    /**
     * Handle the incoming agent prompt.
     *
     * @param mixed $prompt The AgentPrompt instance from Laravel AI SDK.
     * @param Closure $next The next middleware in the pipeline.
     */
    public function handle(mixed $prompt, Closure $next): mixed
    {
        $startTime = hrtime(true);
        $response  = $next($prompt);

        // Track after the response is complete using the SDK's then() hook.
        if (method_exists($response, 'then')) {
            return $response->then(function ($agentResponse) use ($prompt, $startTime) {
                $this->trackExecution($prompt, $agentResponse, $startTime);
            });
        }

        return $response;
    }

    /**
     * Record the prompt execution via PromptDeck's tracking system.
     */
    protected function trackExecution(mixed $prompt, mixed $response, int $startTime): void
    {
        // Only track if the agent uses HasPromptTemplate.
        $agent = $prompt->agent ?? null;

        if ($agent === null || ! method_exists($agent, 'promptName')) {
            return;
        }

        // Converts nanoseconds to milliseconds and calculate latency.
        $latencyMs = (hrtime(true) - $startTime) / 1_000_000;

        $version = method_exists($agent, 'promptTemplate')
            ? $agent->promptTemplate()->version()
            : 0;

        $data = [
            'input'    => $prompt->prompt ?? null,
            'output'   => $response->text ?? null,
            'tokens'   => $response->usage->totalTokens ?? null,
            'latency'  => round($latencyMs, 2),
            'model'    => $response->model ?? null,
            'provider' => $response->provider ?? null,
        ];

        app(PromptManager::class)->track(
            $agent->promptName(),
            $version,
            $data
        );
    }
}
