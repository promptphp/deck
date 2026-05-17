<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Listeners;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Str;

/**
 * Listens for the Laravel AI SDK's `make:agent` command and
 * automatically scaffolds a corresponding Deck prompt.
 *
 * When a developer runs:
 *   php artisan make:agent SalesCoach
 *
 * This listener detects the successful completion and runs:
 *   php artisan make:prompt sales-coach
 *
 * This creates a versioned prompt directory that the agent can
 * use via the HasPromptTemplate trait without any extra setup.
 *
 * The listener is only registered when the Laravel AI SDK is installed.
 */
class AfterMakeAgent
{
    /**
     * Handle the CommandFinished event.
     */
    public function handle(CommandFinished $event): void
    {
        // Only act on `make:agent` commands that succeeded.
        if ($event->command !== 'make:agent' || $event->exitCode !== 0) {
            return;
        }

        // Respect the configuration toggle.
        if (! config('deck.scaffold_on_make_agent', true)) {
            return;
        }

        $agentName = $this->resolveAgentName($event);

        if ($agentName === null) {
            return;
        }

        $promptName = Str::kebab(class_basename($agentName));

        // Silently scaffold the prompt — don't fail the agent creation if this errors.
        try {
            // Check if prompt already exists to avoid interactive prompts.
            $promptPath = config('deck.path').'/'.$promptName;

            if (is_dir($promptPath)) {
                return;
            }

            $exitCode = \Illuminate\Support\Facades\Artisan::call('make:prompt', [
                'name' => $promptName,
            ]);

            if ($exitCode === 0) {
                $event->output->writeln(
                    "<info>Deck:</info> Created prompt <comment>{$promptName}</comment> for agent <comment>{$agentName}</comment>."
                );
            }
        } catch (\Throwable) {
            // Swallow errors — the agent was already created successfully.
            // We don't want Deck to break the make:agent workflow.
        }
    }

    /**
     * Extract the agent class name from the command input.
     */
    protected function resolveAgentName(CommandFinished $event): ?string
    {
        // GeneratorCommand stores the class name as the 'name' argument.
        try {
            $name = $event->input->getArgument('name');
        } catch (\Throwable) {
            return null;
        }

        if (! is_string($name) || $name === '') {
            return null;
        }

        return $name;
    }
}
