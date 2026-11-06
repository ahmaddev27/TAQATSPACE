<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks the receipts a pay action on the invoice wrote, so the admin's
 * mark-unpaid can void exactly those and never one the workspace recorded.
 * legacy_payment_id cannot tell them apart: the legacy-payment import sets it
 * too. Existing rows stay NULL (the cautious side: the undo refuses them).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('receipts', 'source')) {
            return;
        }

        Schema::table('receipts', function (Blueprint $table): void {
            $table->string('source', 30)->nullable()->after('legacy_payment_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('receipts', 'source')) {
            return;
        }

        Schema::table('receipts', function (Blueprint $table): void {
            $table->dropColumn('source');
        });
    }
};
