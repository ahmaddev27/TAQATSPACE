<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an optional self-reported gender to user accounts.
 *
 * Nullable on purpose: existing accounts stay NULL ("unspecified") and nobody is
 * forced to backfill it. Stored as a short string keyed by the {@see \App\Enums\Gender}
 * backed enum, so analytics can group on it with a single indexed aggregate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('gender', 10)->nullable()->after('phone')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['gender']);
            $table->dropColumn('gender');
        });
    }
};
