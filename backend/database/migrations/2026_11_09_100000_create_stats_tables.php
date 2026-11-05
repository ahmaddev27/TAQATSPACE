<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-aggregated figures that serve the dashboards without scanning
 * attendance_daily and hotspot_sessions. Both tables are derived data: a
 * recalculation rewrites a range and gives the same numbers every time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stats_daily_workspace', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            // The workspace-local calendar day.
            $table->date('date');
            $table->unsignedInteger('active_members')->default(0);
            $table->unsignedInteger('dau')->default(0);
            $table->unsignedInteger('active_minutes')->default(0);
            $table->unsignedInteger('hour_bucket_hours')->default(0);
            $table->unsignedBigInteger('bytes_in')->default(0);
            $table->unsignedBigInteger('bytes_out')->default(0);
            $table->unsignedInteger('peak_concurrent')->default(0);
            $table->unsignedTinyInteger('peak_hour')->nullable();
            // 24 counters: distinct subscribers present in each local hour.
            $table->json('hourly_presence')->nullable();

            $table->unique(['workspace_id', 'date']);
        });

        Schema::create('stats_monthly_subscriber', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscriber_id')->constrained('users')->cascadeOnDelete();
            // First day of the workspace-local month.
            $table->date('month');
            $table->unsignedSmallInteger('attendance_days')->default(0);
            $table->decimal('attendance_rate', 5, 2)->default(0);
            $table->unsignedInteger('active_actual_minutes')->default(0);
            $table->unsignedInteger('day_span_minutes')->default(0);
            $table->unsignedInteger('hour_bucket_hours')->default(0);
            $table->unsignedInteger('half_hour_slots')->default(0);
            $table->unsignedBigInteger('bytes_in')->default(0);
            $table->unsignedBigInteger('bytes_out')->default(0);
            // DATETIME: before MariaDB 10.10 two NOT NULL TIMESTAMP columns in one table are rejected (error 1067).
            $table->dateTime('first_activity_at');
            $table->dateTime('last_activity_at');

            $table->unique(['workspace_id', 'subscriber_id', 'month']);
            $table->index(['workspace_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stats_monthly_subscriber');
        Schema::dropIfExists('stats_daily_workspace');
    }
};
