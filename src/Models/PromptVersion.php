<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use PromptPHP\Deck\Database\Factories\PromptVersionFactory;

class PromptVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'version',
        'system_prompt',
        'user_prompt',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'version'   => 'integer',
        'is_active' => 'boolean',
        'metadata'  => 'array',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): PromptVersionFactory
    {
        return PromptVersionFactory::new();
    }
}
