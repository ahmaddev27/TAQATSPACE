<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marks whether a user has chosen and completed their role-specific data.
 *
 * SSO-provisioned accounts are created with this NULL ("needs onboarding") so the
 * app can route them to the role-selection step. Email/password registrations and
 * all pre-existing rows are backfilled as already-onboarded, so nobody is forced
 * through onboarding retroactively.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('onboarding_completed_at')->nullable()->after('sso_sub');
        });

        // Existing accounts have already chosen their role — never re-onboard them.
        DB::table('users')->update(['onboarding_completed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('onboarding_completed_at');
        });
    }
};
