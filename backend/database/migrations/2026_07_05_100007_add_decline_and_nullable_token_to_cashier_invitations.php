<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a `declined_at` timestamp so an invitee who signs in via SSO can decline
 * a pending cashier invitation during onboarding (and proceed to the normal
 * freelancer/owner role choice). Acceptance is now handled by the authenticated,
 * IdP-verified account — the emailed token is no longer the credential — but it
 * stays populated for auditing, so `token_hash` is left as is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_invitations', function (Blueprint $table): void {
            $table->timestamp('declined_at')->nullable()->after('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('cashier_invitations', function (Blueprint $table): void {
            $table->dropColumn('declined_at');
        });
    }
};
