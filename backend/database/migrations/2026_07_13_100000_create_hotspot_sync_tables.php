<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw storage for what routers push to /hotspot/{code}/sync. Events are
 * deduplicated by (router_id, event_uid); snapshots arrive in chunks that are
 * only kept for a week. Events and chunks are high-volume, hence BIGINT keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotspot_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('router_id')->constrained('routers')->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('event_uid', 80);
            $table->string('batch_id', 64);
            $table->string('type', 30);
            $table->string('mac', 17)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('username', 100)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('received_at')->useCurrent();

            $table->unique(['router_id', 'event_uid']);
            $table->index(['router_id', 'occurred_at']);
        });

        Schema::create('router_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('router_id')->constrained('routers')->cascadeOnDelete();
            $table->string('snapshot_id', 64);
            $table->unsignedSmallInteger('total_chunks');
            $table->unsignedSmallInteger('received_chunks')->default(0);
            $table->timestamp('taken_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['router_id', 'snapshot_id']);
        });

        Schema::create('router_snapshot_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('router_snapshot_id')->constrained('router_snapshots')->cascadeOnDelete();
            $table->unsignedSmallInteger('chunk_index');
            $table->longText('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['router_snapshot_id', 'chunk_index']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('router_snapshot_chunks');
        Schema::dropIfExists('router_snapshots');
        Schema::dropIfExists('hotspot_events');
    }
};
