<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PromptPHP\Deck\Models\PromptVersion;

class PromptVersionFactory extends Factory
{
    protected $model = PromptVersion::class;

    public function definition(): array
    {
        return [
            'name'          => $this->faker->slug(2),
            'version'       => $this->faker->numberBetween(1, 10),
            'system_prompt' => $this->faker->paragraph(),
            'user_prompt'   => $this->faker->paragraph(),
            'metadata'      => ['description' => $this->faker->sentence()],
            'is_active'     => false,
        ];
    }

    /**
     * Mark this version as the active version.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Set a specific version number.
     */
    public function version(int $version): static
    {
        return $this->state(fn (array $attributes) => [
            'version' => $version,
        ]);
    }

    /**
     * Set a specific prompt name.
     */
    public function named(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }
}
