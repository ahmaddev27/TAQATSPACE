<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Connected now": one row per device currently on a router's hotspot.
 * Rewritten on every snapshot and event, hence the BIGINT key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_presence', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('router_id')->constrained('routers')->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('mac', 17);
            $table->string('hotspot_username', 100)->nullable();
            $table->string('ip', 45)->nullable();
            $table->unsignedBigInteger('uptime_seconds')->default(0);
            $table->unsignedBigInteger('bytes_in')->default(0);
            $table->unsignedBigInteger('bytes_out')->default(0);
            // DATETIME: before MariaDB 10.10 two NOT NULL TIMESTAMP columns in one table are rejected (error 1067).
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');

            $table->unique(['router_id', 'mac']);
            $table->index(['workspace_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_presence');
    }
};
