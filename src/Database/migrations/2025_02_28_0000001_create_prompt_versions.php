<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('version');
            $table->text('system_prompt')->nullable();
            $table->text('user_prompt')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['name', 'version']);
            $table->index(['name', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('prompt_versions');
    }
};
