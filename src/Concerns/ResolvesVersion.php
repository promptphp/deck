<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Concerns;

/**
 * Trait for resolving prompt versions.
 */
trait ResolvesVersion
{
    /**
     * Parse the version input and return the version number as an integer.
     *
     * @param string $value The version input, e.g. "1" or "v1".
     *
     * @return int|null The parsed version number, or null if invalid.
     */
    public function parseVersion(string $value): ?int
    {
        $value = trim($value);

        if (! preg_match('/^v?([1-9]\d*)$/i', $value, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
