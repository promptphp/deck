<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \PromptPHP\Deck\PromptTemplate get(string $name, ?int $version = null)
 * @method static \PromptPHP\Deck\PromptTemplate active(string $name)
 * @method static array versions(string $name)
 * @method static bool activate(string $name, int $version)
 * @method static void track(string $promptName, int $version, array $data)
 *
 * @see \PromptPHP\Deck\PromptManager
 */
class Deck extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'prompt-deck';
    }
}
