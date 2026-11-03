<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Before MariaDB 10.10 the first NOT NULL TIMESTAMP of a table silently gets
 * ON UPDATE CURRENT_TIMESTAMP, so any update to a payment moved its paid_at.
 * An explicit default removes that clause. It does not restore values already
 * rewritten. Every writer sets these columns, so the default is never used.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'cashier_invitations' => 'expires_at',
        'invoice_payments' => 'paid_at',
        'pos_payments' => 'paid_at',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->timestamp($column)->useCurrent()->change();
            });
        }
    }

    public function down(): void
    {
        // Nothing to undo: putting ON UPDATE back would only restore the bug.
    }
};
