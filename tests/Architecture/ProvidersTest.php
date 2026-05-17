<?php

test('providers extend the base provider class')
    ->expect('PromptPHP\Deck\Providers')
    ->classes()
    ->toExtend(\Illuminate\Support\ServiceProvider::class);
