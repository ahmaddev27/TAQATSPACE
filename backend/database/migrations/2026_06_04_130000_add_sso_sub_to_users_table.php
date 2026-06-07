<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the OIDC subject identifier ("sub") from the Taqat SSO provider so we
 * can match a returning SSO user even if they later change their email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sso_sub')->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['sso_sub']);
            $table->dropColumn('sso_sub');
        });
    }
};
