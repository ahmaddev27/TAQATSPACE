<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-workspace feature flags. A missing row means "disabled", so existing
 * workspaces need no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_features', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 50);
            $table->boolean('enabled')->default(false);
            $table->timestamps();

            $table->unique(['workspace_id', 'feature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_features');
    }
};
