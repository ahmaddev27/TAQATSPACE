<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-4 — Super-admin manual payment tracking. Stores the disk-relative path
 * of the payment receipt the admin uploads when marking an invoice paid.
 * Resolved to a viewable URL via App\Support\MediaUrl.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('receipt_path')->nullable()->after('invoice_pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('receipt_path');
        });
    }
};
