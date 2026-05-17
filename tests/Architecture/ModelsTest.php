<?php

use Illuminate\Database\Eloquent\Model;

test('models extends base model')
    ->expect('PromptPHP\Deck\Models')
    ->classes()
    ->toExtend(Model::class);
