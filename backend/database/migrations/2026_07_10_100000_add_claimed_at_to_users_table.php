<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Supports subscribers without an SSO account: an "unclaimed" user has no
 * `claimed_at`, no password and possibly no email. It is merged into the real
 * SSO identity on first sign-in, so email/phone are the matching keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('claimed_at')->nullable()->after('sso_sub')->index();
            $table->index('phone');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });

        // Every account that exists today signed up itself, so it is already claimed.
        DB::table('users')
            ->whereNull('claimed_at')
            ->update(['claimed_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropIndex(['claimed_at']);
            $table->dropColumn('claimed_at');
        });
    }
};
