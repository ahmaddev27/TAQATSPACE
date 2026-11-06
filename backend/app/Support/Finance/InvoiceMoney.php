<?php

declare(strict_types=1);

namespace App\Support\Finance;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;

/**
 * Single home of the "net of credit notes" money expressions, so every report,
 * dashboard and summary agrees on what an invoice is worth.
 *
 * An invoice keeps its frozen total `amount`; credit notes accumulate in
 * `credited_amount`. Every expression uses portable CASE WHEN (the test DB is
 * SQLite, production is MariaDB) and equals a plain `amount` / `amount_paid`
 * whenever `credited_amount` is 0.
 */
final class InvoiceMoney
{
    /**
     * Statuses whose invoices can still be owed money. The owner aging report
     * defines this set (under_review and payment_rejected stay out), and every
     * outstanding figure reuses it so the dashboards agree with that report.
     *
     * @var list<string>
     */
    public const RECEIVABLE_STATUSES = [
        InvoiceStatus::Pending->value,
        InvoiceStatus::Overdue->value,
        InvoiceStatus::PartiallyPaid->value,
    ];

    /**
     * The invoice total minus its credits, never below zero. This deliberately
     * keeps today's meaning of "sum of amount": older invoices may carry
     * amount_paid = 0 (the column was added later with no backfill), so it
     * cannot be swapped for amount_paid.
     */
    public static function netSql(string $table = 'invoices'): string
    {
        $amount = "{$table}.amount";
        $credited = "{$table}.credited_amount";

        return "(CASE WHEN {$amount} > {$credited} THEN {$amount} - {$credited} ELSE 0 END)";
    }

    /**
     * Money actually kept on the invoice: amount_paid minus the part owed back
     * to the customer after credits. Without credits it is exactly amount_paid,
     * even when that exceeds amount (imported or demo rows).
     */
    public static function collectedSql(string $table = 'invoices'): string
    {
        $net = self::netSql($table);
        $owedBack = "(CASE WHEN {$table}.credited_amount > 0 AND {$table}.amount_paid > {$net}"
            ." THEN {$table}.amount_paid - {$net} ELSE 0 END)";

        return "({$table}.amount_paid - {$owedBack})";
    }

    /**
     * What the customer still owes: amount - credited - paid, never below zero.
     * Only meaningful over {@see RECEIVABLE_STATUSES}: a legacy paid row may
     * carry amount_paid = 0 and would read as owed.
     */
    public static function receivableSql(string $table = 'invoices'): string
    {
        $open = "{$table}.amount - {$table}.credited_amount - {$table}.amount_paid";

        return "(CASE WHEN {$open} > 0 THEN {$open} ELSE 0 END)";
    }

    /** SUM() of the net amount over the given invoice query. */
    public static function sumNet(EloquentBuilder|QueryBuilder $query, string $table = 'invoices'): float
    {
        return (float) $query->sum(new Expression(self::netSql($table)));
    }

    /** SUM() of what is still owed over the given invoice query. */
    public static function sumReceivable(EloquentBuilder|QueryBuilder $query, string $table = 'invoices'): float
    {
        return (float) $query->sum(new Expression(self::receivableSql($table)));
    }

    /** Net amount of one loaded invoice (PHP twin of {@see netSql()}). */
    public static function net(Invoice $invoice): float
    {
        return max(0.0, (float) $invoice->amount - (float) $invoice->credited_amount);
    }

    /** What one loaded invoice still owes (PHP twin of {@see receivableSql()}). */
    public static function receivable(Invoice $invoice): float
    {
        $open = round((float) $invoice->amount - (float) $invoice->credited_amount - (float) $invoice->amount_paid, 2);

        return max(0.0, $open);
    }
}
