<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AR% is empty, not 0, when the subscriber's period holds no operating day.
 * Existing rows keep their stored value until the next statistics recalculation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stats_monthly_subscriber', function (Blueprint $table): void {
            $table->decimal('attendance_rate', 5, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        DB::table('stats_monthly_subscriber')->whereNull('attendance_rate')->update(['attendance_rate' => 0]);

        Schema::table('stats_monthly_subscriber', function (Blueprint $table): void {
            $table->decimal('attendance_rate', 5, 2)->default(0)->change();
        });
    }
};
