<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internet access credentials issued to a member for their subscription. The
 * password is stored encrypted (we need to display/resend it, so it can't be a
 * one-way hash). Provisioning the actual router user is a later phase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('internet_username')->nullable()->unique()->after('seat_id');
            $table->text('internet_password_enc')->nullable()->after('internet_username');
            $table->timestamp('internet_provisioned_at')->nullable()->after('internet_password_enc');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn(['internet_username', 'internet_password_enc', 'internet_provisioned_at']);
        });
    }
};
