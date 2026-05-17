<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Exceptions;

class PromptRenderingException extends DeckException
{
    /**
     * Create a new exception for a missing variable during prompt rendering.
     *
     * @param string $variable The name of the missing variable.
     * @param string $promptName The name of the prompt being rendered.
     */
    public static function dueToMissingVariable(string $variable, string $promptName): self
    {
        return new self("Cannot render prompt [{$promptName}]: missing required variable '{$variable}'.");
    }
}
