<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-to-router command queue (requested, delivered, acknowledged,
 * failed, expired). The payload is stored encrypted, hence a text column.
 * `sync_cycles` counts the syncs since the last delivery and drives resends.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('router_commands', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('router_id')->constrained('routers')->cascadeOnDelete();
            $table->string('verb', 30);
            $table->text('payload');
            $table->string('status', 20)->default('requested');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('sync_cycles')->default(0);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('error', 64)->nullable();
            $table->timestamps();

            $table->index(['router_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('router_commands');
    }
};
