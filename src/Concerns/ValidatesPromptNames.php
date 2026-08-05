<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Concerns;

/**
 * Shared definition of what makes a prompt name usable.
 *
 * Held in one place so the generator and the loader cannot disagree: a name
 * `make:prompt` accepts must be a name `PromptManager` can resolve. They have
 * drifted apart before, over both this and the version directory pattern.
 */
trait ValidatesPromptNames
{
    /**
     * Characters permitted in a prompt name.
     *
     * Names are interpolated into filesystem paths, so anything that could
     * escape the prompts directory is rejected. A leading dot is disallowed,
     * which also rules out '..'.
     *
     * Anchored with \A and \z rather than ^ and $, because $ also matches
     * before a trailing newline — "order-summary\n" would otherwise pass.
     */
    protected const NAME_PATTERN = '/\A[A-Za-z0-9_-][A-Za-z0-9._-]*\z/';

    /**
     * Determine whether a prompt name is safe to resolve into a path.
     */
    protected function isValidPromptName(string $name): bool
    {
        return preg_match(self::NAME_PATTERN, $name) === 1;
    }
}
