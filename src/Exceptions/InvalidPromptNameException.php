<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Exceptions;

class InvalidPromptNameException extends DeckException
{
    /**
     * Create a new exception for a prompt name that is unsafe to resolve.
     *
     * Names become filesystem paths, so one containing a directory separator
     * or a leading dot could read files outside the configured prompts path.
     *
     * @param string $name The rejected prompt name.
     */
    public static function named(string $name): self
    {
        return new self(
            "Prompt name [{$name}] is not valid. Names may contain letters, numbers, "
            .'dots, dashes, and underscores, and may not begin with a dot.'
        );
    }
}
