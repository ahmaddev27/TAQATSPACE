<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periods in which a router sent nothing we can trust, so the days they touch
 * are not read as absence. A gap is open (no ended_at) while the router is
 * still silent; the daily statistics also carry has_gap / gap_minutes so a
 * dashboard can tell a quiet day from a day with no data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('router_gaps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('router_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            // DATETIME: before MariaDB 10.10 two NOT NULL TIMESTAMP columns in one table are rejected (error 1067).
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('reason', 16);
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['router_id', 'started_at']);
            $table->index(['workspace_id', 'started_at']);
        });

        Schema::table('stats_daily_workspace', function (Blueprint $table): void {
            $table->boolean('has_gap')->default(false);
            $table->unsignedSmallInteger('gap_minutes')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('stats_daily_workspace', function (Blueprint $table): void {
            $table->dropColumn(['has_gap', 'gap_minutes']);
        });

        Schema::dropIfExists('router_gaps');
    }
};
