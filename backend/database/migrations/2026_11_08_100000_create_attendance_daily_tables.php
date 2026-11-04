<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per subscriber per local day, derived from hotspot_sessions: a
 * 48-slot half-hour bitmap plus the four attendance modes computed from it.
 * attendance_affected_days is the work list of days whose sessions changed
 * since the last rebuild (a table, because shared hosting has no Redis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_daily', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscriber_id')->constrained('users')->cascadeOnDelete();
            // The workspace-local calendar day, not a UTC day.
            $table->date('date');
            // Bit n = half-hour n of the local wall clock (0 = 00:00-00:30, 47 = 23:30-24:00).
            $table->unsignedBigInteger('slots_bitmap')->default(0);
            $table->unsignedSmallInteger('active_actual_minutes')->default(0);
            $table->unsignedSmallInteger('day_span_minutes')->default(0);
            $table->unsignedTinyInteger('hour_bucket_hours')->default(0);
            $table->unsignedTinyInteger('half_hour_slots')->default(0);
            // DATETIME: before MariaDB 10.10 two NOT NULL TIMESTAMP columns in one table are rejected (error 1067).
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            // Filled by the gap detection of task 3.6.
            $table->boolean('has_gap')->default(false);

            $table->unique(['workspace_id', 'subscriber_id', 'date']);
            $table->index(['workspace_id', 'date']);
        });

        Schema::create('attendance_affected_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            // A mark newer than the rebuild that claimed it survives that rebuild.
            $table->dateTime('marked_at');

            $table->unique(['workspace_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_affected_days');
        Schema::dropIfExists('attendance_daily');
    }
};
