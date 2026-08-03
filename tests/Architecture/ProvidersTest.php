<?php

use Illuminate\Support\ServiceProvider;

test('providers extend the base provider class')
    ->expect('PromptPHP\Deck\Providers')
    ->classes()
    ->toExtend(ServiceProvider::class);
