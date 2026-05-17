<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

test('PromptDeck provider class extends base Laravel service provider class')
    ->expect('PromptPHP\Deck\Providers\PromptDeckServiceProvider')
    ->classes()
    ->toExtend(ServiceProvider::class);

test('PromptDeck facade class extends base Laravel facade class')
    ->expect('PromptPHP\Deck\Facades\PromptDeck')
    ->classes()
    ->toExtend(Facade::class);
