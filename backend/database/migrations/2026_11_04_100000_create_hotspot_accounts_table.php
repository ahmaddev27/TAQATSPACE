<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One hotspot account per subscription. The username is unique per workspace
 * only, so two workspaces may hand out the same login on their own routers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotspot_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('username', 64);
            $table->text('password_enc');
            $table->string('profile', 64)->nullable();
            $table->string('status', 20)->default('active');
            $table->dateTime('provisioned_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'username']);
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotspot_accounts');
    }
};
