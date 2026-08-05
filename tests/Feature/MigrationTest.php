<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PromptPHP\Deck\Models\PromptExecution;
use PromptPHP\Deck\Models\PromptVersion;

/*
|--------------------------------------------------------------------------
| Helper: Run the package migrations against the test DB
|--------------------------------------------------------------------------
*/

function runMigrations(): void
{
    $migrationsPath = realpath(__DIR__.'/../../src/Database/migrations');

    test()->artisan('migrate', [
        '--path'     => $migrationsPath,
        '--realpath' => true,
        '--database' => 'testing',
    ])->assertSuccessful();
}

function rollbackMigrations(): void
{
    $migrationsPath = realpath(__DIR__.'/../../src/Database/migrations');

    test()->artisan('migrate:rollback', [
        '--path'     => $migrationsPath,
        '--realpath' => true,
        '--database' => 'testing',
    ])->assertSuccessful();
}

// =====================================================================
// prompt_versions migration — schema
// =====================================================================

test('prompt_versions table is created by migration', function () {
    runMigrations();

    expect(Schema::connection('testing')->hasTable('prompt_versions'))->toBeTrue();
});

test('prompt_versions table has all expected columns', function () {
    runMigrations();

    $columns = Schema::connection('testing')->getColumnListing('prompt_versions');

    expect($columns)->toContain('id')
        ->toContain('name')
        ->toContain('version')
        ->toContain('system_prompt')
        ->toContain('user_prompt')
        ->toContain('metadata')
        ->toContain('is_active')
        ->toContain('created_at')
        ->toContain('updated_at');
});

test('prompt_versions name column is a string', function () {
    runMigrations();

    $type = Schema::connection('testing')->getColumnType('prompt_versions', 'name');

    expect($type)->toBeIn(['string', 'varchar', 'text']);
});

test('prompt_versions version column is an unsigned integer', function () {
    runMigrations();

    $type = Schema::connection('testing')->getColumnType('prompt_versions', 'version');

    expect($type)->toBeIn(['integer', 'int', 'bigint']);
});

test('prompt_versions system_prompt column is nullable', function () {
    runMigrations();

    // Insert a row with null system_prompt — should succeed.
    DB::connection('testing')->table('prompt_versions')->insert([
        'name'        => 'test-nullable',
        'version'     => 1,
        'user_prompt' => 'Hello {{ $name }}',
        'is_active'   => false,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $record = DB::connection('testing')->table('prompt_versions')
        ->where('name', 'test-nullable')->first();

    expect($record->system_prompt)->toBeNull();
});

test('prompt_versions is_active defaults to false', function () {
    runMigrations();

    DB::connection('testing')->table('prompt_versions')->insert([
        'name'        => 'default-test',
        'version'     => 1,
        'user_prompt' => 'prompt content',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $record = DB::connection('testing')->table('prompt_versions')
        ->where('name', 'default-test')->first();

    expect((bool) $record->is_active)->toBeFalse();
});

test('prompt_versions enforces unique constraint on name + version', function () {
    runMigrations();

    DB::connection('testing')->table('prompt_versions')->insert([
        'name'        => 'unique-test',
        'version'     => 1,
        'user_prompt' => 'first',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    // Inserting a duplicate name+version should throw.
    DB::connection('testing')->table('prompt_versions')->insert([
        'name'        => 'unique-test',
        'version'     => 1,
        'user_prompt' => 'duplicate',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
})->throws(QueryException::class);

test('prompt_versions allows same name with different versions', function () {
    runMigrations();

    DB::connection('testing')->table('prompt_versions')->insert([
        'name'        => 'multi-ver',
        'version'     => 1,
        'user_prompt' => 'v1',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    DB::connection('testing')->table('prompt_versions')->insert([
        'name'        => 'multi-ver',
        'version'     => 2,
        'user_prompt' => 'v2',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $count = DB::connection('testing')->table('prompt_versions')
        ->where('name', 'multi-ver')->count();

    expect($count)->toBe(2);
});

test('prompt_versions allows different names with same version', function () {
    runMigrations();

    DB::connection('testing')->table('prompt_versions')->insert([
        'name'        => 'prompt-a',
        'version'     => 1,
        'user_prompt' => 'content a',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    DB::connection('testing')->table('prompt_versions')->insert([
        'name'        => 'prompt-b',
        'version'     => 1,
        'user_prompt' => 'content b',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $count = DB::connection('testing')->table('prompt_versions')->count();

    expect($count)->toBe(2);
});

test('prompt_versions metadata column accepts JSON', function () {
    runMigrations();

    $metadata = json_encode(['description' => 'Test prompt', 'tags' => ['ai', 'chat']]);

    DB::connection('testing')->table('prompt_versions')->insert([
        'name'        => 'json-test',
        'version'     => 1,
        'user_prompt' => 'content',
        'metadata'    => $metadata,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $record = DB::connection('testing')->table('prompt_versions')
        ->where('name', 'json-test')->first();

    $decoded = json_decode($record->metadata, true);

    expect($decoded['description'])->toBe('Test prompt')
        ->and($decoded['tags'])->toBe(['ai', 'chat']);
});

test('prompt_versions metadata column is nullable', function () {
    runMigrations();

    DB::connection('testing')->table('prompt_versions')->insert([
        'name'        => 'no-meta',
        'version'     => 1,
        'user_prompt' => 'content',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $record = DB::connection('testing')->table('prompt_versions')
        ->where('name', 'no-meta')->first();

    expect($record->metadata)->toBeNull();
});

// =====================================================================
// prompt_executions migration — schema
// =====================================================================

test('prompt_executions table is created by migration', function () {
    runMigrations();

    expect(Schema::connection('testing')->hasTable('prompt_executions'))->toBeTrue();
});

test('prompt_executions table has all expected columns', function () {
    runMigrations();

    $columns = Schema::connection('testing')->getColumnListing('prompt_executions');

    expect($columns)->toContain('id')
        ->toContain('prompt_name')
        ->toContain('prompt_version')
        ->toContain('input')
        ->toContain('output')
        ->toContain('tokens')
        ->toContain('latency_ms')
        ->toContain('cost')
        ->toContain('model')
        ->toContain('provider')
        ->toContain('feedback')
        ->toContain('created_at')
        ->toContain('updated_at');
});

test('prompt_executions accepts a full record with all fields', function () {
    runMigrations();

    DB::connection('testing')->table('prompt_executions')->insert([
        'prompt_name'    => 'greeting',
        'prompt_version' => 1,
        'input'          => json_encode(['message' => 'hello']),
        'output'         => 'Hi there! How can I help?',
        'tokens'         => 150,
        'latency_ms'     => 234,
        'cost'           => 0.002100,
        'model'          => 'gpt-4',
        'provider'       => 'openai',
        'feedback'       => json_encode(['rating' => 5]),
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    $record = DB::connection('testing')->table('prompt_executions')
        ->where('prompt_name', 'greeting')->first();

    expect($record)->not->toBeNull()
        ->and($record->prompt_name)->toBe('greeting')
        ->and($record->prompt_version)->toBe(1)
        ->and($record->output)->toBe('Hi there! How can I help?')
        ->and($record->tokens)->toBe(150)
        ->and($record->model)->toBe('gpt-4')
        ->and($record->provider)->toBe('openai');
});

test('prompt_executions nullable fields accept null values', function () {
    runMigrations();

    DB::connection('testing')->table('prompt_executions')->insert([
        'prompt_name'    => 'minimal',
        'prompt_version' => 1,
        'input'          => null,
        'output'         => null,
        'tokens'         => null,
        'latency_ms'     => null,
        'cost'           => null,
        'model'          => null,
        'provider'       => null,
        'feedback'       => null,
        'created_at'     => now(),
    ]);

    $record = DB::connection('testing')->table('prompt_executions')
        ->where('prompt_name', 'minimal')->first();

    expect($record->input)->toBeNull()
        ->and($record->output)->toBeNull()
        ->and($record->tokens)->toBeNull()
        ->and($record->latency_ms)->toBeNull()
        ->and($record->cost)->toBeNull()
        ->and($record->model)->toBeNull()
        ->and($record->provider)->toBeNull()
        ->and($record->feedback)->toBeNull();
});

test('prompt_executions cost column stores decimal precision', function () {
    runMigrations();

    DB::connection('testing')->table('prompt_executions')->insert([
        'prompt_name'    => 'cost-test',
        'prompt_version' => 1,
        'cost'           => 0.000123,
        'created_at'     => now(),
    ]);

    $record = DB::connection('testing')->table('prompt_executions')
        ->where('prompt_name', 'cost-test')->first();

    // SQLite stores decimals as text/float — verify it's approximately correct.
    expect((float) $record->cost)->toBeGreaterThan(0.0001)
        ->and((float) $record->cost)->toBeLessThan(0.001);
});

test('prompt_executions allows multiple records for same prompt', function () {
    runMigrations();

    for ($i = 0; $i < 3; $i++) {
        DB::connection('testing')->table('prompt_executions')->insert([
            'prompt_name'    => 'repeated',
            'prompt_version' => 1,
            'output'         => "response {$i}",
            'created_at'     => now(),
        ]);
    }

    $count = DB::connection('testing')->table('prompt_executions')
        ->where('prompt_name', 'repeated')->count();

    expect($count)->toBe(3);
});

test('prompt_executions input column stores JSON data', function () {
    runMigrations();

    $inputData = ['messages' => [['role' => 'user', 'content' => 'Hello']]];

    DB::connection('testing')->table('prompt_executions')->insert([
        'prompt_name'    => 'json-input',
        'prompt_version' => 1,
        'input'          => json_encode($inputData),
        'created_at'     => now(),
    ]);

    $record = DB::connection('testing')->table('prompt_executions')
        ->where('prompt_name', 'json-input')->first();

    $decoded = json_decode($record->input, true);
    expect($decoded['messages'][0]['role'])->toBe('user')
        ->and($decoded['messages'][0]['content'])->toBe('Hello');
});

test('prompt_executions feedback column stores JSON', function () {
    runMigrations();

    $feedback = ['rating' => 4, 'comment' => 'Good response', 'tags' => ['accurate', 'helpful']];

    DB::connection('testing')->table('prompt_executions')->insert([
        'prompt_name'    => 'feedback-test',
        'prompt_version' => 1,
        'feedback'       => json_encode($feedback),
        'created_at'     => now(),
    ]);

    $record = DB::connection('testing')->table('prompt_executions')
        ->where('prompt_name', 'feedback-test')->first();

    $decoded = json_decode($record->feedback, true);
    expect($decoded['rating'])->toBe(4)
        ->and($decoded['tags'])->toBe(['accurate', 'helpful']);
});

// =====================================================================
// Rollback — both migrations
// =====================================================================

test('prompt_versions migration can be rolled back', function () {
    runMigrations();

    expect(Schema::connection('testing')->hasTable('prompt_versions'))->toBeTrue();

    rollbackMigrations();

    expect(Schema::connection('testing')->hasTable('prompt_versions'))->toBeFalse();
});

test('prompt_executions migration can be rolled back', function () {
    runMigrations();

    expect(Schema::connection('testing')->hasTable('prompt_executions'))->toBeTrue();

    rollbackMigrations();

    expect(Schema::connection('testing')->hasTable('prompt_executions'))->toBeFalse();
});

test('rollback removes both tables', function () {
    runMigrations();

    expect(Schema::connection('testing')->hasTable('prompt_versions'))->toBeTrue()
        ->and(Schema::connection('testing')->hasTable('prompt_executions'))->toBeTrue();

    rollbackMigrations();

    expect(Schema::connection('testing')->hasTable('prompt_versions'))->toBeFalse()
        ->and(Schema::connection('testing')->hasTable('prompt_executions'))->toBeFalse();
});

test('migrations can be re-run after rollback', function () {
    runMigrations();
    rollbackMigrations();
    runMigrations();

    expect(Schema::connection('testing')->hasTable('prompt_versions'))->toBeTrue()
        ->and(Schema::connection('testing')->hasTable('prompt_executions'))->toBeTrue();
});

// =====================================================================
// Factory / model integration
// =====================================================================

test('PromptVersion factory creates valid records', function () {
    runMigrations();

    $version = PromptVersion::factory()
        ->named('factory-prompt')
        ->version(1)
        ->active()
        ->create();

    expect($version)->toBeInstanceOf(PromptVersion::class)
        ->and($version->name)->toBe('factory-prompt')
        ->and($version->version)->toBe(1)
        ->and($version->is_active)->toBeTrue()
        ->and($version->user_prompt)->not->toBeEmpty();
});

test('PromptVersion factory defaults is_active to false', function () {
    runMigrations();

    $version = PromptVersion::factory()->create();

    expect($version->is_active)->toBeFalse();
});

test('PromptVersion factory active() state sets is_active to true', function () {
    runMigrations();

    $version = PromptVersion::factory()->active()->create();

    expect($version->is_active)->toBeTrue();
});

test('PromptVersion factory can create multiple versions for same prompt', function () {
    runMigrations();

    PromptVersion::factory()->named('multi')->version(1)->create();
    PromptVersion::factory()->named('multi')->version(2)->create();
    PromptVersion::factory()->named('multi')->version(3)->active()->create();

    $count = PromptVersion::where('name', 'multi')->count();

    expect($count)->toBe(3);

    $active = PromptVersion::where('name', 'multi')->where('is_active', true)->first();
    expect($active->version)->toBe(3);
});

test('PromptVersion factory stores metadata as JSON', function () {
    runMigrations();

    $version = PromptVersion::factory()->create([
        'metadata' => ['description' => 'Test', 'tags' => ['ai']],
    ]);

    $version->refresh();

    expect($version->metadata)->toBe(['description' => 'Test', 'tags' => ['ai']]);
});

test('PromptExecution factory creates valid records', function () {
    runMigrations();

    $execution = PromptExecution::factory()
        ->forPrompt('test-prompt', 2)
        ->create();

    expect($execution)->toBeInstanceOf(PromptExecution::class)
        ->and($execution->prompt_name)->toBe('test-prompt')
        ->and($execution->prompt_version)->toBe(2)
        ->and($execution->output)->not->toBeEmpty();
});

test('PromptExecution factory minimal() state sets optional fields to null', function () {
    runMigrations();

    $execution = PromptExecution::factory()->minimal()->create();

    expect($execution->input)->toBeNull()
        ->and($execution->output)->toBeNull()
        ->and($execution->tokens)->toBeNull()
        ->and($execution->model)->toBeNull();
});

test('PromptExecution factory withFeedback() state includes feedback', function () {
    runMigrations();

    $execution = PromptExecution::factory()
        ->withFeedback(['rating' => 5, 'comment' => 'Excellent'])
        ->create();

    expect($execution->feedback)->toBe(['rating' => 5, 'comment' => 'Excellent']);
});

test('PromptExecution factory can create multiple executions', function () {
    runMigrations();

    PromptExecution::factory()->forPrompt('batch', 1)->count(5)->create();

    $count = PromptExecution::where('prompt_name', 'batch')->count();

    expect($count)->toBe(5);
});

test('PromptExecution factory model is one of the expected values', function () {
    runMigrations();

    $execution = PromptExecution::factory()->create();

    expect($execution->model)->toBeIn(['gpt-4', 'gpt-4o', 'claude-3-opus', 'claude-3-sonnet']);
});

test('PromptExecution factory provider is one of the expected values', function () {
    runMigrations();

    $execution = PromptExecution::factory()->create();

    expect($execution->provider)->toBeIn(['openai', 'anthropic']);
});

test('prompt_versions user_prompt is nullable so activate() can record a version', function () {
    runMigrations();

    DB::connection('testing')->table('prompt_versions')->insert([
        'name'       => 'content-free',
        'version'    => 1,
        'is_active'  => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $record = DB::connection('testing')->table('prompt_versions')
        ->where('name', 'content-free')->first();

    expect($record->user_prompt)->toBeNull()
        ->and($record->system_prompt)->toBeNull();
});
