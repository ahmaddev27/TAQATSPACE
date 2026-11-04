<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connection sessions of a device on a router, built from events, snapshots,
 * timeouts and reboots. The source of truth for durations (attendance, usage).
 * Written in bulk by the event pipeline, hence the BIGINT key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotspot_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('router_id')->constrained('routers')->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignUuid('subscriber_id')->nullable()->constrained('users')->nullOnDelete();
            // DATETIME: before MariaDB 10.10 two NOT NULL TIMESTAMP columns in one table are rejected (error 1067).
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            // Last moment the router vouched for the session; a session that ends without a logout is closed here.
            $table->dateTime('last_seen_at');
            $table->dateTime('last_snapshot_at')->nullable();
            $table->unsignedSmallInteger('missed_snapshots')->default(0);
            $table->string('end_reason', 20)->nullable();
            $table->unsignedBigInteger('bytes_in')->default(0);
            $table->unsignedBigInteger('bytes_out')->default(0);
            $table->string('source', 10);

            $table->index(['workspace_id', 'started_at']);
            $table->index(['router_id', 'ended_at']);
            $table->index(['device_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotspot_sessions');
    }
};
