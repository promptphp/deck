<?php

declare(strict_types=1);

namespace PromptPHP\Deck\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use PromptPHP\Deck\Database\Factories\PromptExecutionFactory;

class PromptExecution extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'prompt_name',
        'prompt_version',
        'input',
        'output',
        'tokens',
        'latency_ms',
        'cost',
        'model',
        'provider',
        'feedback',
    ];

    protected $casts = [
        'prompt_version' => 'integer',
        'tokens'         => 'integer',
        'latency_ms'     => 'integer',
        'cost'           => 'decimal:6',
        'input'          => 'array',
        'feedback'       => 'array',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): PromptExecutionFactory
    {
        return PromptExecutionFactory::new();
    }
}
