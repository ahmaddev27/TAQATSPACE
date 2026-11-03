<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every MAC ever seen on a workspace's hotspot, optionally linked to the
 * subscriber who owns it. Created by the event pipeline, hence the BIGINT key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscriber_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mac', 17);
            $table->string('label', 100)->nullable();
            $table->string('vendor', 100)->nullable();
            $table->string('device_type', 30)->nullable();
            $table->boolean('is_randomized_mac')->default(false);
            $table->string('status', 20)->default('discovered');
            // DATETIME: before MariaDB 10.10 two NOT NULL TIMESTAMP columns in one table are rejected (error 1067).
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');

            $table->unique(['workspace_id', 'mac']);
            $table->index(['workspace_id', 'subscriber_id']);
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
