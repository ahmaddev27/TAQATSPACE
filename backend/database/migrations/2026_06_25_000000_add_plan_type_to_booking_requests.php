<?php

declare(strict_types=1);

use App\Enums\PlanType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a freelancer choose a monthly or daily plan when requesting to join a
 * workspace. The chosen plan drives the subscription's term + price on approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_requests', function (Blueprint $table): void {
            $table->string('plan_type')->default(PlanType::Monthly->value)->after('preferred_seat_type');
        });
    }

    public function down(): void
    {
        Schema::table('booking_requests', function (Blueprint $table): void {
            $table->dropColumn('plan_type');
        });
    }
};
