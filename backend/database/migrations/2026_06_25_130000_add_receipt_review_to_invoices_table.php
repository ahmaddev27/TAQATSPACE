<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receipt-review fields: when an owner rejects a member-submitted payment
 * receipt, the reason is recorded and shown back to the member so they can fix
 * it and re-upload. `receipt_reviewed_at` stamps the owner's decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->text('receipt_rejected_reason')->nullable()->after('receipt_path');
            $table->timestamp('receipt_reviewed_at')->nullable()->after('receipt_rejected_reason');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['receipt_rejected_reason', 'receipt_reviewed_at']);
        });
    }
};
