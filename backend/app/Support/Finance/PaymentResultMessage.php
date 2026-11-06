<?php

declare(strict_types=1);

namespace App\Support\Finance;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

/**
 * The message for a "pay what is left" action, worded from the status the
 * invoice actually ended in: with finance v2 a pending cheque can leave it
 * partially paid, and saying "marked paid" then would be wrong.
 */
final class PaymentResultMessage
{
    public static function for(Invoice $invoice, string $paidKey = 'messages.invoice_marked_paid'): string
    {
        return $invoice->status === InvoiceStatus::PartiallyPaid
            ? __('messages.invoice_marked_partially_paid')
            : __($paidKey);
    }
}
