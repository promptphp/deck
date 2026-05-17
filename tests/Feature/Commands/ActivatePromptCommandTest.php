<?php

declare(strict_types=1);

test('prompt:activate activates version and outputs success message', function () {
    $this->createPromptFixture('act-prompt', 1, 'sys v1', 'usr v1');
    $this->createPromptFixture('act-prompt', 2, 'sys v2', 'usr v2');

    $this->artisan('prompt:activate', ['name' => 'act-prompt', 'version' => 2])
        ->expectsOutput('Version 2 of prompt [act-prompt] activated.')
        ->assertSuccessful();

    $meta = json_decode(file_get_contents("{$this->tempDir}/act-prompt/metadata.json"), true);
    expect($meta['active_version'])->toBe(2);
});

test('prompt:activate returns failure when exception is thrown', function () {
    // We mock the PromptManager to throw an exception.
    $mock = \Mockery::mock(\PromptPHP\Deck\PromptManager::class);
    $mock->shouldReceive('activate')
        ->with('bad-prompt', 1)
        ->andThrow(new \Exception('Something went wrong'));

    $this->app->instance(\PromptPHP\Deck\PromptManager::class, $mock);

    $this->artisan('prompt:activate', ['name' => 'bad-prompt', 'version' => 1])
        ->expectsOutput('Something went wrong')
        ->assertFailed();
});
