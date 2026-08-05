<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deck is file-based: prompt content lives on disk, and prompt_versions only
 * records which version is active. activate() inserts rows carrying no
 * content, so user_prompt can no longer be NOT NULL.
 *
 * A no-op for installs published after this release, where the create
 * migration already declares the column nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompt_versions', function (Blueprint $table) {
            $table->text('user_prompt')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows written by activate() carry no content, so they must go before
        // the column can be NOT NULL again.
        Schema::table('prompt_versions', function (Blueprint $table) {
            $table->text('user_prompt')->nullable(false)->default('')->change();
        });
    }
};
