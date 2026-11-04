<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Donor-required subscriber demographics. All nullable so existing accounts
 * survive; `gender` and the legacy free-text `specialty` already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('education_level', 30)->nullable();
            $table->string('specialization', 40)->nullable();
            $table->string('employment_status', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['education_level', 'specialization', 'employment_status']);
        });
    }
};
