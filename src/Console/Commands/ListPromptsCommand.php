<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Console\Commands;

use Illuminate\Console\Command;
use PromptPHP\Deck\PromptManager;

class ListPromptsCommand extends Command
{
    protected $signature = 'prompt:list {--all : Show all versions for each prompt}';

    protected $description = 'List all available prompts';

    protected PromptManager $manager;

    public function __construct(PromptManager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
    }

    public function handle(): int
    {
        $basePath = config('prompt-deck.path');
        if (! is_dir($basePath)) {
            $this->warn('Prompts directory not found.');

            return Command::SUCCESS;
        }

        $prompts = glob($basePath.'/*', GLOB_ONLYDIR);
        if (empty($prompts)) {
            $this->info('No prompts found.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($prompts as $promptDir) {
            $name          = basename($promptDir);
            $activeVersion = $this->getActiveVersion($name);

            if ($this->option('all')) {
                $versions = $this->manager->versions($name);
                foreach ($versions as $v) {
                    $rows[] = [
                        $name,
                        'v'.$v['version'],
                        $v['version'] === $activeVersion ? '✅' : '',
                        $v['metadata']['description'] ?? '',
                    ];
                }
            } else {
                $rows[] = [
                    $name,
                    'v'.$activeVersion,
                    '✅',
                    $this->getPromptDescription($name, $activeVersion),
                ];
            }
        }

        $this->table(['Prompt', 'Active Version', 'Active', 'Description'], $rows);

        return Command::SUCCESS;
    }

    /**
     * Get the active version number for a prompt, or 0 if not found.
     */
    protected function getActiveVersion(string $name): int
    {
        try {
            return $this->manager->active($name)->version();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get the description for a specific prompt version, or an empty string if not found.
     */
    protected function getPromptDescription(string $name, int $version): string
    {
        try {
            $prompt = $this->manager->get($name, $version);

            return $prompt->metadata()['description'] ?? '';
        } catch (\Exception $e) {
            return '';
        }
    }
}
