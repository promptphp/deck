<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Console\Commands;

use Illuminate\Console\Command;
use PromptPHP\Deck\PromptManager;

class TestPromptCommand extends Command
{
    protected $signature = 'prompt:test {name : The prompt name}
                              {--ver= : Specific version (defaults to active)}
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
        $version       = $this->option('ver') ? (int) $this->option('ver') : null;
        $input         = $this->option('input') ?? 'Sample user input';
        $variablesJson = $this->option('variables') ?? '{}';

        $variables = json_decode($variablesJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON for --variables');

            return Command::FAILURE;
        }

        try {
            $prompt = $version
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
