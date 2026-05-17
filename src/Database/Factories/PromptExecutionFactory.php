<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PromptPHP\Deck\Models\PromptExecution;

class PromptExecutionFactory extends Factory
{
    protected $model = PromptExecution::class;

    public function definition(): array
    {
        return [
            'prompt_name'    => $this->faker->slug(2),
            'prompt_version' => $this->faker->numberBetween(1, 5),
            'input'          => ['message' => $this->faker->sentence()],
            'output'         => $this->faker->paragraph(),
            'tokens'         => $this->faker->numberBetween(50, 5000),
            'latency_ms'     => $this->faker->numberBetween(100, 3000),
            'cost'           => $this->faker->randomFloat(6, 0.000001, 0.1),
            'model'          => $this->faker->randomElement(['gpt-4', 'gpt-4o', 'claude-3-opus', 'claude-3-sonnet']),
            'provider'       => $this->faker->randomElement(['openai', 'anthropic']),
            'feedback'       => null,
        ];
    }

    /**
     * Include user feedback in the execution record.
     */
    public function withFeedback(array $feedback = []): static
    {
        return $this->state(fn (array $attributes) => [
            'feedback' => $feedback ?: ['rating' => $this->faker->numberBetween(1, 5), 'comment' => $this->faker->sentence()],
        ]);
    }

    /**
     * Create a minimal execution record with only required fields.
     */
    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'input'      => null,
            'output'     => null,
            'tokens'     => null,
            'latency_ms' => null,
            'cost'       => null,
            'model'      => null,
            'provider'   => null,
            'feedback'   => null,
        ]);
    }

    /**
     * Set a specific prompt name and version.
     */
    public function forPrompt(string $name, int $version): static
    {
        return $this->state(fn (array $attributes) => [
            'prompt_name'    => $name,
            'prompt_version' => $version,
        ]);
    }
}
