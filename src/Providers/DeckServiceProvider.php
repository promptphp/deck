<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Providers;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use PromptPHP\Deck\Console\Commands\ActivatePromptCommand;
use PromptPHP\Deck\Console\Commands\ListPromptsCommand;
use PromptPHP\Deck\Console\Commands\MakePromptCommand;
use PromptPHP\Deck\Console\Commands\PromptDiffCommand;
use PromptPHP\Deck\Console\Commands\TestPromptCommand;
use PromptPHP\Deck\PromptManager;

class DeckServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerArtisanCommands();
        $this->registerAiSdkIntegration();
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->configure();
    }

    /**
     * Setup the configuration for Deck.
     */
    protected function configure(): void
    {
        // Merge config.
        $this->mergeConfigFrom(
            __DIR__.'/../../config/deck.php', 'deck'
        );

        // Register the main manager as a singleton.
        $this->app->singleton(PromptManager::class, function ($app) {
            return new PromptManager(
                config('deck.path'),
                config('deck.extension'),
                $app['cache']->store(config('deck.cache.store')),
                $app['config']
            );
        });

        // Register a facade alias.
        $this->app->alias(PromptManager::class, 'deck');
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        // Publish migrations.
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'deck-migrations');

            // Publish config.
            $this->publishes([
                __DIR__.'/../../config/deck.php' => config_path('deck.php'),
            ], 'deck-config');
        }
    }

    /**
     * Register Artisan commands for Deck.
     */
    protected function registerArtisanCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakePromptCommand::class,
                ListPromptsCommand::class,
                ActivatePromptCommand::class,
                PromptDiffCommand::class,
                TestPromptCommand::class,
            ]);
        }
    }

    /**
     * Register bindings for Laravel AI SDK integration.
     */
    protected function registerAiSdkIntegration(): void
    {
        if (class_exists(\Laravel\Ai\AiServiceProvider::class)) {
            $this->app->singleton(\PromptPHP\Deck\Ai\TrackPromptMiddleware::class);

            // Auto-scaffold a prompt when `make:agent` finishes successfully.
            if (config('deck.scaffold_on_make_agent', true)) {
                Event::listen(
                    CommandFinished::class,
                    \PromptPHP\Deck\Listeners\AfterMakeAgent::class
                );
            }
        }
    }
}
