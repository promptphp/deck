<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Exceptions;

class InvalidVersionException extends DeckException
{
    /**
     * * Create an exception for a non-existent version.
     *
     * @param string $name The name of the prompt.
     * @param int $version The version number that was not found.
     */
    public static function forPrompt(string $name, int $version): self
    {
        return new self("Version {$version} for prompt [{$name}] does not exist.");
    }

    /**
     * Create an exception when no versions are found for a prompt.
     */
    public static function noVersions(string $name): self
    {
        return new self("No versions found for prompt [{$name}].");
    }
}
