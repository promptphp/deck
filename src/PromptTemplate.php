<?php

declare(strict_types=1);

namespace PromptPHP\Deck;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @method string system(array $variables = [])
 * @method string user(array $variables = [])
 * @method string assistant(array $variables = [])
 * @method string developer(array $variables = [])
 * @method string tool(array $variables = [])
 * @method string skill(array $variables = [])
 */
class PromptTemplate implements Arrayable
{
    /**
     * @param string $name The prompt name.
     * @param int $version The resolved version number.
     * @param array<string, string> $roles Role name → raw content map.
     * @param array $metadata Prompt-level metadata.
     */
    public function __construct(
        protected string $name,
        protected int $version,
        protected array $roles = [],
        protected array $metadata = [],
    ) {}

    /**
     * Render a role's content with variable interpolation.
     *
     * @param string $role The role name (e.g. 'system', 'user', 'assistant').
     * @param array $variables Key-value pairs to interpolate.
     *
     * @return string The role's content with variables interpolated. Returns empty string if role not found.
     */
    public function role(string $role, array $variables = []): string
    {
        return $this->interpolate($this->roles[$role] ?? '', $variables);
    }

    /**
     * Get the raw content for a role without interpolation.
     *
     * @param string $role The role name (e.g. 'system', 'user', 'assistant').
     *
     * @return string The raw content for the role, or empty string if role not found.
     */
    public function raw(string $role): string
    {
        return $this->roles[$role] ?? '';
    }

    /**
     * Determine if a given role exists in this prompt.
     *
     * @param string $role The role name (e.g. 'system', 'user', 'assistant').
     *
     * @return bool True if the role exists, false otherwise.
     */
    public function has(string $role): bool
    {
        return isset($this->roles[$role]);
    }

    /**
     * Get all available role names.
     *
     * @return list<string> The list of role names defined in this prompt (e.g. ['system', 'user', 'assistant']).
     */
    public function roles(): array
    {
        return array_keys($this->roles);
    }

    /**
     * Build a messages array ready for AI API consumption.
     *
     * Returns an array of ['role' => '...', 'content' => '...'] entries,
     * suitable for OpenAI, Anthropic, or any chat-completion API.
     *
     * @param array $variables Variables to interpolate into every role.
     * @param list<string>|null $only Limit to these roles (preserves order). Null = all.
     *
     * @return list<array{role: string, content: string}> The rendered messages array. Roles not found in the prompt are skipped.
     */
    public function toMessages(array $variables = [], ?array $only = null): array
    {
        $roleNames = $only ?? array_keys($this->roles);
        $messages  = [];

        foreach ($roleNames as $roleName) {
            if (isset($this->roles[$roleName])) {
                $messages[] = [
                    'role'    => $roleName,
                    'content' => $this->interpolate($this->roles[$roleName], $variables),
                ];
            }
        }

        return $messages;
    }

    /**
     * Get the resolved version number.
     *
     * @return int The resolved version number.
     */
    public function version(): int
    {
        return $this->version;
    }

    /**
     * Get the prompt name.
     *
     * @return string The prompt name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Get all metadata for the prompt.
     *
     * @return array The prompt metadata as an associative array. May be empty if no metadata is defined.
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * Convert the prompt to array (useful for caching).
     *
     * @return array The prompt as an associative array.
     */
    public function toArray(): array
    {
        return [
            'name'     => $this->name,
            'version'  => $this->version,
            'roles'    => $this->roles,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Dynamic role access via method call.
     *
     * Allows expressive syntax like:
     *   $prompt->system(['tone' => 'friendly'])
     *   $prompt->assistant(['context' => '...'])
     *
     * @param string $method The role name.
     * @param array $parameters First element is the variables array.
     *
     * @return string The rendered content for the role, or empty string if role not found.
     */
    public function __call(string $method, array $parameters): string
    {
        return $this->role($method, $parameters[0] ?? []);
    }

    /**
     * Simple variable interpolation using {{ $var }} syntax.
     */
    protected function interpolate(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace(
                ['{{ $'.$key.' }}', '{{'.$key.'}}'],
                (string) $value,
                $content
            );
        }

        return $content;
    }
}
