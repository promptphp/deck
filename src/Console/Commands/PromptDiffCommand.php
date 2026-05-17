<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;
use PromptPHP\Deck\Exceptions\PromptNotFoundException;

class PromptDiffCommand extends Command
{
    protected $signature = 'prompt:diff {name : The prompt name}
                              {--v1= : First version number}
                              {--v2= : Second version number}
                              {--type= : system, user, or all (default)}';

    protected $description = 'Show differences between two prompt versions';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $name = $this->argument('name');
        $v1   = $this->option('v1');
        $v2   = $this->option('v2');
        $type = $this->option('type') ?? 'all';

        if (! $v1 || ! $v2) {
            $this->error('Both --v1 and --v2 are required.');

            return Command::FAILURE;
        }

        $basePath = config('deck.path').'/'.$name;

        if (! $this->files->isDirectory($basePath)) {
            throw PromptNotFoundException::named($name);
        }

        $ext = config('deck.extension', 'md');

        $filesToCompare = [];
        if ($type === 'system' || $type === 'all') {
            $filesToCompare[] = ['system.'.$ext, 'System Prompt'];
        }
        if ($type === 'user' || $type === 'all') {
            $filesToCompare[] = ['user.'.$ext, 'User Prompt'];
        }

        foreach ($filesToCompare as [$file, $label]) {
            $path1 = $basePath.'/v'.$v1.'/'.$file;
            $path2 = $basePath.'/v'.$v2.'/'.$file;

            if (! $this->files->exists($path1) && ! $this->files->exists($path2)) {
                continue;
            }

            $this->info("\n--- {$label} ---");

            $content1 = $this->files->exists($path1) ? $this->files->get($path1) : '';
            $content2 = $this->files->exists($path2) ? $this->files->get($path2) : '';

            $this->showDiff($content1, $content2);
        }

        return Command::SUCCESS;
    }

    protected function showDiff(string $old, string $new): void
    {
        $differ = new Differ(new UnifiedDiffOutputBuilder);
        $output = $differ->diff($old, $new);

        $this->line($output);
    }
}
