<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time install tokens for a router's install.rsc. Only the token hash is
 * stored; the router key it delivers is held encrypted until the single
 * successful fetch, then cleared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('router_install_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('router_id')->constrained('routers')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->text('encrypted_key')->nullable();
            // DATETIME: before MariaDB 10.10 the first NOT NULL TIMESTAMP is rewritten on every row update.
            $table->dateTime('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['router_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('router_install_tokens');
    }
};
