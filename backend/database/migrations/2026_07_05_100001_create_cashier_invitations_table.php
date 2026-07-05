<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pending email invitations an owner sends to onboard a café/cashier staff
 * member. The invitee follows the tokenised link, sets a password, and a
 * `cashier` account bound to the workspace is created on acceptance.
 *
 * The token is stored hashed (it is a credential), single-use, and expires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUuid('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('token_hash')->unique();
            $table->json('permissions')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignUuid('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['workspace_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_invitations');
    }
};
