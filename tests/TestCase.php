<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use PromptPHP\Deck\Providers\PromptDeckServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/prompt-deck-tests-'.uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->app['config']->set('prompt-deck.path', $this->tempDir);
        $this->app['config']->set('prompt-deck.extension', 'md');
        $this->app['config']->set('prompt-deck.cache.enabled', false);
        $this->app['config']->set('prompt-deck.tracking.enabled', false);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            PromptDeckServiceProvider::class,
        ];
    }

    /**
     * Set up in-memory database and other environment configuration for testing.
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', [
            'driver'    => 'array',
            'serialize' => false,
        ]);
    }

    /**
     * Create a prompt fixture on disk for testing.
     */
    protected function createPromptFixture(
        string $name,
        int $version = 1,
        ?string $systemContent = null,
        ?string $userContent = null,
        ?array $metadata = null,
        ?array $promptMetadata = null,
        string $extension = 'md',
    ): string {
        $versionPath = "{$this->tempDir}/{$name}/v{$version}";

        if (! is_dir($versionPath)) {
            mkdir($versionPath, 0755, true);
        }

        if ($systemContent !== null) {
            file_put_contents("{$versionPath}/system.{$extension}", $systemContent);
        }

        if ($userContent !== null) {
            file_put_contents("{$versionPath}/user.{$extension}", $userContent);
        }

        if ($metadata !== null) {
            file_put_contents("{$versionPath}/metadata.json", json_encode($metadata, JSON_PRETTY_PRINT));
        }

        if ($promptMetadata !== null) {
            file_put_contents("{$this->tempDir}/{$name}/metadata.json", json_encode($promptMetadata, JSON_PRETTY_PRINT));
        }

        return $versionPath;
    }

    /**
     * Recursively delete a directory.
     */
    protected function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    /**
     * Set up the database tables needed for tracking tests.
     */
    protected function setUpTrackingTables(): void
    {
        $this->app['db']->connection('testing')->getSchemaBuilder()->create('prompt_versions', function ($table) {
            $table->id();
            $table->string('name');
            $table->integer('version');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        $this->app['db']->connection('testing')->getSchemaBuilder()->create('prompt_executions', function ($table) {
            $table->id();
            $table->string('prompt_name');
            $table->integer('prompt_version');
            $table->text('input')->nullable();
            $table->text('output')->nullable();
            $table->integer('tokens')->nullable();
            $table->float('latency_ms')->nullable();
            $table->float('cost')->nullable();
            $table->string('model')->nullable();
            $table->string('provider')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
