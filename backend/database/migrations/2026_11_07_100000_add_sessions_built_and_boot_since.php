<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sessions_built_at` marks events whose sessions were built, so a job that
 * died between applying and building can build them on retry.
 * `sessions_boot_since` is when the current sessions boot was first seen,
 * to tell a late event of an older boot from a newer boot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotspot_events', function (Blueprint $table): void {
            $table->timestamp('sessions_built_at')->nullable();
            $table->index(['router_id', 'sessions_built_at']);
        });

        Schema::table('routers', function (Blueprint $table): void {
            $table->timestamp('sessions_boot_since')->nullable();
        });

        // Events processed before this column existed were already built.
        DB::table('hotspot_events')->whereNotNull('processed_at')->update(['sessions_built_at' => DB::raw('processed_at')]);
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table): void {
            $table->dropColumn('sessions_boot_since');
        });

        Schema::table('hotspot_events', function (Blueprint $table): void {
            $table->dropIndex(['router_id', 'sessions_built_at']);
            $table->dropColumn('sessions_built_at');
        });
    }
};
