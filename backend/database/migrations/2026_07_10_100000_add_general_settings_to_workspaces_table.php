<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** General settings: contact, bank details, locale (time zone/currency) and Wi-Fi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->string('contact_email', 191)->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('timezone', 64)->default('Asia/Gaza');
            $table->string('currency', 3)->default('ILS');
            $table->string('wifi_ssid', 64)->nullable();
            // Holds the encrypted payload, which is far longer than the password.
            $table->text('wifi_password')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropColumn([
                'contact_email', 'bank_name', 'iban', 'timezone', 'currency', 'wifi_ssid', 'wifi_password',
            ]);
        });
    }
};
