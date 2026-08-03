<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Concerns;

use Laravel\Ai\Messages\Message;
use PromptPHP\Deck\PromptManager;
use PromptPHP\Deck\PromptTemplate;
use Stringable;

/**
 * Trait for integrating Deck templates with Laravel AI SDK agents.
 *
 * Provides automatic loading of system instructions and conversation messages
 * from versioned prompt files managed by Deck. Use this trait alongside
 * the Promptable trait in your Agent classes:
 *
 *   class SalesCoach implements Agent, Conversational
 *   {
 *       use Promptable, HasPromptTemplate;
 *
 *       // instructions() is provided by this trait automatically.
 *       // Override promptName(), promptVersion(), and promptVariables()
 *       // to customise prompt loading.
 *   }
 *
 * @see https://laravel.com/docs/ai-sdk
 */
trait HasPromptTemplate
{
    /**
     * The cached PromptTemplate instance.
     */
    protected ?PromptTemplate $cachedPromptTemplate = null;

    /**
     * Get the prompt name to load from Deck.
     *
     * Defaults to the kebab-cased class name (e.g. SalesCoach → sales-coach).
     * Override this method to use a custom prompt name.
     *
     * @return string The prompt name to load.
     */
    public function promptName(): string
    {
        return str(class_basename($this))->kebab()->toString();
    }

    /**
     * Get the prompt version to load.
     *
     * Return null to use the active version (default).
     * Override this to pin the agent to a specific prompt version.
     *
     * @return int|null The prompt version to load, or null for active version.
     */
    public function promptVersion(): ?int
    {
        return null;
    }

    /**
     * Get variables for prompt template interpolation.
     *
     * Override this to provide dynamic context variables
     * that will be injected into all prompt roles.
     *
     * @return array<string, mixed> The variables for prompt template interpolation.
     */
    public function promptVariables(): array
    {
        return [];
    }

    /**
     * Get the loaded PromptTemplate instance.
     *
     * The template is cached for the lifetime of the agent instance
     * to avoid redundant filesystem/cache lookups.
     *
     * @return PromptTemplate The loaded prompt template instance.
     */
    public function promptTemplate(): PromptTemplate
    {
        if ($this->cachedPromptTemplate === null) {
            $this->cachedPromptTemplate = app(PromptManager::class)->get(
                $this->promptName(),
                $this->promptVersion()
            );
        }

        return $this->cachedPromptTemplate;
    }

    /**
     * Get the agent's system instructions from the prompt template.
     *
     * Loads the 'system' role content from the Deck template
     * and interpolates variables from promptVariables().
     *
     * This satisfies the Laravel AI SDK Agent contract's instructions() method.
     *
     * @return Stringable|string The rendered system instructions for the agent.
     */
    public function instructions(): Stringable|string
    {
        return $this->promptTemplate()->system($this->promptVariables());
    }

    /**
     * Get prompt roles converted to Laravel AI SDK Message objects.
     *
     * Converts Deck template roles into Message instances
     * suitable for the Conversational contract's messages() method.
     * By default excludes the 'system' role (which goes through instructions()).
     *
     * When the Laravel AI SDK is not installed, returns raw arrays
     * with 'role' and 'content' keys instead.
     *
     * @param list<string>|null $only Limit to these roles. Null = all non-system roles.
     *
     * @return array<int, mixed> Message objects (when AI SDK is available) or raw arrays.
     */
    public function promptMessages(?array $only = null): array
    {
        // By default, exclude 'system' since it goes through instructions().
        $only ??= array_values(array_filter(
            $this->promptTemplate()->roles(),
            fn (string $role) => $role !== 'system'
        ));

        $rawMessages = $this->promptTemplate()->toMessages(
            $this->promptVariables(),
            $only
        );

        // Convert to Laravel AI SDK Message objects if available.
        if (class_exists(Message::class)) {
            return array_map(
                fn (array $msg) => new Message($msg['role'], $msg['content']),
                $rawMessages
            );
        }

        return $rawMessages;
    }

    /**
     * Clear the cached PromptTemplate, forcing a fresh load on next access.
     *
     * Useful for long-running processes or when testing with
     * different prompt versions.
     */
    public function forgetPromptTemplate(): static
    {
        $this->cachedPromptTemplate = null;

        return $this;
    }
}
