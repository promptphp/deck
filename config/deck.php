<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Prompts Path
    |--------------------------------------------------------------------------
    |
    | This path determines where your versioned prompt files are stored.
    | By default, they live in 'resources/prompts'.
    |
    */
    'path' => resource_path('prompts'),

    /*
    |--------------------------------------------------------------------------
    | Default Prompt Extension
    |--------------------------------------------------------------------------
    |
    | The file extension used for prompt templates. Markdown is recommended
    | for readability, but you can change it to 'txt', 'blade.php', etc.
    |
    */
    'extension' => 'md',

    /*
    |--------------------------------------------------------------------------
    | Versioning Strategy
    |--------------------------------------------------------------------------
    |
    | How versions are stored: 'directory' (v1/, v2/ inside prompt folder)
    | or 'file' (prompt_v1.md, prompt_v2.md). Default is 'directory'.
    |
    */
    'versioning' => 'directory',

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Enable caching of compiled prompts for better performance.
    |
    */
    'cache' => [
        'enabled' => env('DECK_CACHE_ENABLED', env('APP_DEBUG', false) ? false : true),
        'store'   => env('DECK_CACHE_STORE', 'file'), // null to use default cache
        'ttl'     => env('DECK_CACHE_TTL', 3600), // seconds
        'prefix'  => env('CACHE_PREFIX', env('DECK_CACHE_PREFIX', 'deck:')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Tracking
    |--------------------------------------------------------------------------
    |
    | If enabled, prompt versions and executions will be logged to the database,
    | enabling performance tracking and audit trails.
    |
    | Tracking is off by default because it requires the package migrations to
    | have been published and run. Prompt rendering never depends on it: if the
    | tables are missing or the database is unreachable, Deck falls back to
    | metadata.json and logs a warning rather than failing.
    |
    | To enable it:
    |   1. php artisan vendor:publish --tag=deck-migrations
    |   2. php artisan migrate
    |   3. DECK_TRACKING_ENABLED=true
    |
    */
    'tracking' => [
        'enabled'    => env('DECK_TRACKING_ENABLED', false),
        'connection' => env('DECK_DB_CONNECTION'), // null for default
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Scaffold Prompt on make:agent
    |--------------------------------------------------------------------------
    |
    | When the Laravel AI SDK is installed and this option is enabled,
    | Deck will automatically create a matching prompt directory
    | whenever you run `php artisan make:agent`. Set to false to disable.
    |
    */
    'scaffold_on_make_agent' => env('DECK_SCAFFOLD_ON_MAKE_AGENT', true),
];
