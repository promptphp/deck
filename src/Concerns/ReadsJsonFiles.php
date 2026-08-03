<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Concerns;

/**
 * Trait for reading Deck's JSON sidecar files.
 *
 * Metadata files are hand-editable, so they may be missing entirely or
 * contain invalid JSON. Neither should bring down prompt rendering.
 *
 * The using class must expose an `Illuminate\Filesystem\Filesystem`
 * as a `$files` property.
 */
trait ReadsJsonFiles
{
    /**
     * Read and decode a JSON file.
     *
     * @param string $path The absolute path to the JSON file.
     *
     * @return array<string, mixed> The decoded contents, or an empty array when the
     *                              file is absent, unreadable, or is not a JSON object.
     */
    protected function readJson(string $path): array
    {
        if (! $this->files->exists($path)) {
            return [];
        }

        $decoded = json_decode($this->files->get($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
