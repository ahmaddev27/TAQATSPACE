<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MikroTik routers (PUSH model) and their API request log. Only SHA-256 hashes
 * of router keys are stored; the previous hash stays valid until its expiry so
 * a key can be rotated without cutting the router off. The log is high-volume,
 * hence the BIGINT key, and is pruned after 14 days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key_hash', 64)->unique();
            $table->string('previous_key_hash', 64)->nullable()->index();
            $table->timestamp('previous_key_expires_at')->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('routeros_version', 50)->nullable();
            $table->string('script_version', 50)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedBigInteger('last_seq')->default(0);
            $table->integer('clock_offset_seconds')->nullable();
            $table->timestamps();
        });

        Schema::create('router_api_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('router_id')->constrained('routers')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('response_code');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->unsignedInteger('body_size')->default(0);
            $table->text('error_message')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['router_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('router_api_logs');
        Schema::dropIfExists('routers');
    }
};
