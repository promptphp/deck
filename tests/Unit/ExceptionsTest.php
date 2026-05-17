<?php

declare(strict_types=1);

use PromptPHP\Deck\Exceptions\ConfigurationException;
use PromptPHP\Deck\Exceptions\InvalidVersionException;
use PromptPHP\Deck\Exceptions\PromptDeckException;
use PromptPHP\Deck\Exceptions\PromptNotFoundException;
use PromptPHP\Deck\Exceptions\PromptRenderingException;

// --- Hierarchy ---

test('PromptDeckException extends base Exception', function () {
    expect(PromptDeckException::class)
        ->toExtend(\Exception::class);
});

test('ConfigurationException extends PromptDeckException', function () {
    $e = ConfigurationException::invalidPath('/some/path');

    expect($e)->toBeInstanceOf(PromptDeckException::class)
        ->and($e)->toBeInstanceOf(\Exception::class);
});

test('InvalidVersionException extends PromptDeckException', function () {
    $e = InvalidVersionException::forPrompt('test', 1);

    expect($e)->toBeInstanceOf(PromptDeckException::class);
});

test('PromptNotFoundException extends PromptDeckException', function () {
    $e = PromptNotFoundException::named('test');

    expect($e)->toBeInstanceOf(PromptDeckException::class);
});

test('PromptRenderingException extends PromptDeckException', function () {
    $e = PromptRenderingException::dueToMissingVariable('name', 'greeting');

    expect($e)->toBeInstanceOf(PromptDeckException::class);
});

// --- Message format ---

test('ConfigurationException::invalidPath() includes the path in the message', function () {
    $e = ConfigurationException::invalidPath('/invalid/path');

    expect($e->getMessage())
        ->toBe('Prompts path [/invalid/path] is not a directory or is not writable.');
});

test('InvalidVersionException::forPrompt() includes name and version in the message', function () {
    $e = InvalidVersionException::forPrompt('my-prompt', 5);

    expect($e->getMessage())
        ->toBe('Version 5 for prompt [my-prompt] does not exist.');
});

test('InvalidVersionException::noVersions() includes name in the message', function () {
    $e = InvalidVersionException::noVersions('empty-prompt');

    expect($e->getMessage())
        ->toBe('No versions found for prompt [empty-prompt].');
});

test('PromptNotFoundException::named() includes name in the message', function () {
    $e = PromptNotFoundException::named('missing-prompt');

    expect($e->getMessage())
        ->toBe('Prompt [missing-prompt] not found.');
});

test('PromptRenderingException::dueToMissingVariable() includes variable and prompt name', function () {
    $e = PromptRenderingException::dueToMissingVariable('username', 'login-prompt');

    expect($e->getMessage())
        ->toBe("Cannot render prompt [login-prompt]: missing required variable 'username'.");
});

// --- Edge cases ---

test('exception messages handle special characters in names', function () {
    $e = PromptNotFoundException::named('my/special prompt');

    expect($e->getMessage())
        ->toBe('Prompt [my/special prompt] not found.');
});

test('exception messages handle empty strings', function () {
    $e = PromptNotFoundException::named('');

    expect($e->getMessage())
        ->toBe('Prompt [] not found.');
});
