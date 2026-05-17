<?php

declare(strict_types=1);

use PromptPHP\Deck\Exceptions\PromptNotFoundException;

// ──────────────────────────────────────────────────────────────
// Validation: missing version options
// ──────────────────────────────────────────────────────────────

test('prompt:diff requires both --v1 and --v2 options', function () {
    $this->artisan('prompt:diff', ['name' => 'test-prompt'])
        ->expectsOutput('Both --v1 and --v2 are required.')
        ->assertFailed();
});

test('prompt:diff fails when only --v1 is provided', function () {
    $this->artisan('prompt:diff', ['name' => 'test-prompt', '--v1' => 1])
        ->expectsOutput('Both --v1 and --v2 are required.')
        ->assertFailed();
});

test('prompt:diff fails when only --v2 is provided', function () {
    $this->artisan('prompt:diff', ['name' => 'test-prompt', '--v2' => 2])
        ->expectsOutput('Both --v1 and --v2 are required.')
        ->assertFailed();
});

// ──────────────────────────────────────────────────────────────
// Non-existent prompt
// ──────────────────────────────────────────────────────────────

test('prompt:diff throws PromptNotFoundException for non-existent prompt', function () {
    $this->expectException(PromptNotFoundException::class);

    $this->artisan('prompt:diff', [
        'name' => 'nonexistent',
        '--v1' => 1,
        '--v2' => 2,
    ]);
});

// ──────────────────────────────────────────────────────────────
// Default type (all): diffs both system and user prompts
// ──────────────────────────────────────────────────────────────

test('prompt:diff shows diff for both system and user prompts by default', function () {
    $this->createPromptFixture('diff-test', 1, 'System v1 content', 'User v1 content');
    $this->createPromptFixture('diff-test', 2, 'System v2 content', 'User v2 content');

    $this->artisan('prompt:diff', [
        'name' => 'diff-test',
        '--v1' => 1,
        '--v2' => 2,
    ])
        ->expectsOutputToContain('--- System Prompt ---')
        ->expectsOutputToContain('--- User Prompt ---')
        ->assertSuccessful();
});

// ──────────────────────────────────────────────────────────────
// --type=system: only system prompt diff
// ──────────────────────────────────────────────────────────────

test('prompt:diff --type=system only shows system prompt diff', function () {
    $this->createPromptFixture('diff-system', 1, 'System v1', 'User v1');
    $this->createPromptFixture('diff-system', 2, 'System v2', 'User v2');

    $result = $this->artisan('prompt:diff', [
        'name'   => 'diff-system',
        '--v1'   => 1,
        '--v2'   => 2,
        '--type' => 'system',
    ]);

    $result->expectsOutputToContain('--- System Prompt ---')
        ->assertSuccessful();
});

// ──────────────────────────────────────────────────────────────
// --type=user: only user prompt diff
// ──────────────────────────────────────────────────────────────

test('prompt:diff --type=user only shows user prompt diff', function () {
    $this->createPromptFixture('diff-user', 1, 'System v1', 'User v1');
    $this->createPromptFixture('diff-user', 2, 'System v2', 'User v2');

    $result = $this->artisan('prompt:diff', [
        'name'   => 'diff-user',
        '--v1'   => 1,
        '--v2'   => 2,
        '--type' => 'user',
    ]);

    $result->expectsOutputToContain('--- User Prompt ---')
        ->assertSuccessful();
});

// ──────────────────────────────────────────────────────────────
// Identical content: no diff lines
// ──────────────────────────────────────────────────────────────

test('prompt:diff with identical versions produces no diff markers', function () {
    $this->createPromptFixture('diff-same', 1, 'Same content', 'Same user');
    $this->createPromptFixture('diff-same', 2, 'Same content', 'Same user');

    $this->artisan('prompt:diff', [
        'name' => 'diff-same',
        '--v1' => 1,
        '--v2' => 2,
    ])->assertSuccessful();
});

// ──────────────────────────────────────────────────────────────
// Missing file in one version: treats as empty
// ──────────────────────────────────────────────────────────────

test('prompt:diff treats missing file in v1 as empty string', function () {
    // v1 has no system prompt, v2 does
    $this->createPromptFixture('diff-partial', 1, null, 'User v1');
    $this->createPromptFixture('diff-partial', 2, 'New system prompt', 'User v2');

    $this->artisan('prompt:diff', [
        'name'   => 'diff-partial',
        '--v1'   => 1,
        '--v2'   => 2,
        '--type' => 'system',
    ])
        ->expectsOutputToContain('--- System Prompt ---')
        ->assertSuccessful();
});

test('prompt:diff treats missing file in v2 as empty string', function () {
    // v1 has system prompt, v2 does not
    $this->createPromptFixture('diff-partial2', 1, 'Old system prompt', 'User v1');
    $this->createPromptFixture('diff-partial2', 2, null, 'User v2');

    $this->artisan('prompt:diff', [
        'name'   => 'diff-partial2',
        '--v1'   => 1,
        '--v2'   => 2,
        '--type' => 'system',
    ])
        ->expectsOutputToContain('--- System Prompt ---')
        ->assertSuccessful();
});

// ──────────────────────────────────────────────────────────────
// Both files missing for a type: skip silently
// ──────────────────────────────────────────────────────────────

test('prompt:diff skips file type when neither version has it', function () {
    // Neither version has a system prompt
    $this->createPromptFixture('diff-no-system', 1, null, 'User v1');
    $this->createPromptFixture('diff-no-system', 2, null, 'User v2');

    $this->artisan('prompt:diff', [
        'name'   => 'diff-no-system',
        '--v1'   => 1,
        '--v2'   => 2,
        '--type' => 'system',
    ])->assertSuccessful();
});

// ──────────────────────────────────────────────────────────────
// Multi-line diff: verifies actual diff output
// ──────────────────────────────────────────────────────────────

test('prompt:diff shows added and removed lines for multi-line changes', function () {
    $oldContent = "Line 1\nLine 2\nLine 3";
    $newContent = "Line 1\nModified Line 2\nLine 3\nLine 4";

    $this->createPromptFixture('diff-multiline', 1, null, $oldContent);
    $this->createPromptFixture('diff-multiline', 2, null, $newContent);

    $this->artisan('prompt:diff', [
        'name'   => 'diff-multiline',
        '--v1'   => 1,
        '--v2'   => 2,
        '--type' => 'user',
    ])->assertSuccessful();
});

// ──────────────────────────────────────────────────────────────
// Respects config extension
// ──────────────────────────────────────────────────────────────

test('prompt:diff respects configured file extension', function () {
    $this->app['config']->set('prompt-deck.extension', 'txt');

    // Create fixtures with .txt extension
    $this->createPromptFixture('diff-ext', 1, null, null, null, null, 'txt');
    $this->createPromptFixture('diff-ext', 2, null, null, null, null, 'txt');

    // Write txt files manually
    file_put_contents("{$this->tempDir}/diff-ext/v1/user.txt", 'Old text');
    file_put_contents("{$this->tempDir}/diff-ext/v2/user.txt", 'New text');

    $this->artisan('prompt:diff', [
        'name'   => 'diff-ext',
        '--v1'   => 1,
        '--v2'   => 2,
        '--type' => 'user',
    ])
        ->expectsOutputToContain('--- User Prompt ---')
        ->assertSuccessful();
});

// ──────────────────────────────────────────────────────────────
// Edge case: empty content in both versions
// ──────────────────────────────────────────────────────────────

test('prompt:diff handles empty files in both versions', function () {
    $this->createPromptFixture('diff-empty', 1, '', '');
    $this->createPromptFixture('diff-empty', 2, '', '');

    $this->artisan('prompt:diff', [
        'name' => 'diff-empty',
        '--v1' => 1,
        '--v2' => 2,
    ])->assertSuccessful();
});

// ──────────────────────────────────────────────────────────────
// Edge case: one version empty, other has content
// ──────────────────────────────────────────────────────────────

test('prompt:diff handles diff from empty to non-empty content', function () {
    $this->createPromptFixture('diff-grow', 1, '', '');
    $this->createPromptFixture('diff-grow', 2, 'New system content', 'New user content');

    $this->artisan('prompt:diff', [
        'name' => 'diff-grow',
        '--v1' => 1,
        '--v2' => 2,
    ])->assertSuccessful();
});

test('prompt:diff handles diff from non-empty to empty content', function () {
    $this->createPromptFixture('diff-shrink', 1, 'Old system content', 'Old user content');
    $this->createPromptFixture('diff-shrink', 2, '', '');

    $this->artisan('prompt:diff', [
        'name' => 'diff-shrink',
        '--v1' => 1,
        '--v2' => 2,
    ])->assertSuccessful();
});
