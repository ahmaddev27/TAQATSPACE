<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks which raw events were turned into state and which snapshots were
 * reassembled, so processing can be retried without repeating work.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotspot_events', function (Blueprint $table): void {
            $table->timestamp('processed_at')->nullable();
            $table->index(['router_id', 'processed_at']);
        });

        Schema::table('router_snapshots', function (Blueprint $table): void {
            $table->timestamp('assembled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('router_snapshots', function (Blueprint $table): void {
            $table->dropColumn('assembled_at');
        });

        Schema::table('hotspot_events', function (Blueprint $table): void {
            $table->dropIndex(['router_id', 'processed_at']);
            $table->dropColumn('processed_at');
        });
    }
};
