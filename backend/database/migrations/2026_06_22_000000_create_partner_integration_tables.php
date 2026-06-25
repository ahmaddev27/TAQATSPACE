<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partner integration: machine-to-machine clients (e.g. the Academy platform)
 * and an audit/retry log of the webhooks we deliver to them.
 *
 * `partner_clients` carries a hashed API key (the plaintext is shown once at
 * creation) for server-to-server auth, plus an optional webhook endpoint +
 * signing secret. `webhook_deliveries` records every outbound webhook so a
 * failed delivery can be inspected and retried without losing the event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_clients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('api_key_hash')->unique();
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('scopes')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('partner_client_id')->constrained()->cascadeOnDelete();
            $table->string('event')->index();
            $table->json('payload');
            $table->string('status')->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('partner_clients');
    }
};
