<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-device FCM registration tokens. One user may have many tokens (phone,
 * tablet, multiple browsers); each token is globally unique. Tokens cascade
 * away with the owning user and are pruned automatically when FCM reports them
 * unregistered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            // FCM tokens are ~160 chars today; 512 leaves generous headroom and,
            // unlike TEXT, can carry the UNIQUE index without a prefix length.
            $table->string('token', 512);
            $table->string('platform')->nullable();
            $table->timestamps();

            $table->unique('token');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
