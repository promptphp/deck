<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Console\Commands;

use Illuminate\Console\Command;
use PromptPHP\Deck\PromptManager;

class ActivatePromptCommand extends Command
{
    protected $signature = 'prompt:activate {name : The prompt name}
                              {version : The version number to activate, e.g. 1 or v1}';

    protected $description = 'Activate a specific version of a prompt';

    protected PromptManager $manager;

    public function __construct(PromptManager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
    }

    public function handle(): int
    {
        $name         = (string) $this->argument('name');
        $versionInput = (string) $this->argument('version');

        $version = $this->parseVersion($versionInput);

        if ($version === null) {
            $this->error("Invalid version [{$versionInput}] provided. Use a positive number like [1] or [v1].");

            return Command::FAILURE;
        }

        try {
            $this->manager->activate($name, $version);

            $this->info("Version {$versionInput} of prompt [{$name}] activated.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Parse the version input and return the version number as an integer.
     *
     * @param string $value The version input, e.g. "1" or "v1".
     *
     * @return int|null The parsed version number, or null if invalid.
     */
    protected function parseVersion(string $value): ?int
    {
        $value = trim($value);

        if (! preg_match('/^v?([1-9]\d*)$/i', $value, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
