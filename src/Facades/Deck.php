<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Facades;

use Illuminate\Support\Facades\Facade;
use PromptPHP\Deck\PromptManager;

/**
 * @method static \PromptPHP\Deck\PromptTemplate get(string $name, string|int|null $version = null)
 * @method static \PromptPHP\Deck\PromptTemplate active(string $name)
 * @method static array versions(string $name)
 * @method static bool activate(string $name, int $version)
 * @method static void track(string $promptName, int $version, array $data)
 *
 * @see PromptManager
 */
class Deck extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'deck';
    }
}
