<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Console\Commands;

use Illuminate\Console\Command;
use PromptPHP\Deck\PromptManager;

class ActivatePromptCommand extends Command
{
    protected $signature = 'prompt:activate {name : The prompt name}
                              {version : The version number to activate}';

    protected $description = 'Activate a specific version of a prompt';

    protected PromptManager $manager;

    public function __construct(PromptManager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
    }

    public function handle(): int
    {
        $name    = $this->argument('name');
        $version = (int) $this->argument('version');

        try {
            $this->manager->activate($name, $version);
            $this->info("Version {$version} of prompt [{$name}] activated.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
