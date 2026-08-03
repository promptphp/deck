<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Console\Commands;

use Illuminate\Console\Command;
use PromptPHP\Deck\Concerns\ResolvesVersion;
use PromptPHP\Deck\PromptManager;

class TestPromptCommand extends Command
{
    use ResolvesVersion;

    protected $signature = 'prompt:test {name : The prompt name}
                              {--ver= : Specific version, e.g. 2 or v2 (defaults to active)}
                              {--input= : The input to test}
                              {--variables= : JSON string of variables}';

    protected $description = 'Test a prompt with sample input and see the rendered result';

    protected PromptManager $manager;

    public function __construct(PromptManager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
    }

    public function handle(): int
    {
        $name          = $this->argument('name');
        $versionInput  = $this->option('ver');
        $input         = $this->option('input') ?? 'Sample user input';
        $variablesJson = $this->option('variables') ?? '{}';

        $variables = json_decode($variablesJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON for --variables');

            return Command::FAILURE;
        }

        // A null version means "use whichever version is active".
        $version = null;

        if ($versionInput !== null && $versionInput !== '') {
            $version = $this->parseVersion((string) $versionInput);

            if ($version === null) {
                $this->error("Invalid version [{$versionInput}] provided. Use a positive number like [1] or [v1].");

                return Command::FAILURE;
            }
        }

        try {
            $prompt = $version !== null
                ? $this->manager->get($name, $version)
                : $this->manager->active($name);

            $this->info("Testing prompt [{$name}] version {$prompt->version()}\n");

            if ($prompt->metadata()['variables'] ?? false) {
                $this->comment('Expected variables: '.implode(', ', $prompt->metadata()['variables']));
            }

            $this->line("\n--- SYSTEM PROMPT ---");
            $this->line($prompt->system($variables));

            $this->line("\n--- USER PROMPT ---");
            $this->line($prompt->user(array_merge($variables, ['input' => $input])));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
