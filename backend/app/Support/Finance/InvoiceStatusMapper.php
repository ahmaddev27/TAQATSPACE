<?php

declare(strict_types=1);

namespace App\Support\Finance;

use App\Enums\DocStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;

/**
 * Translates between the legacy single `status` and the v2 pair
 * (`doc_status`, `payment_status`) so both stay in step on every write.
 */
final class InvoiceStatusMapper
{
    /** @return array{0: DocStatus, 1: PaymentStatus} */
    public static function fromLegacy(InvoiceStatus $status): array
    {
        return match ($status) {
            InvoiceStatus::PartiallyPaid => [DocStatus::Issued, PaymentStatus::Partial],
            InvoiceStatus::Paid => [DocStatus::Issued, PaymentStatus::Paid],
            InvoiceStatus::Cancelled => [DocStatus::Void, PaymentStatus::Unpaid],
            default => [DocStatus::Issued, PaymentStatus::Unpaid],
        };
    }

    /**
     * An unpaid issued invoice keeps its current legacy status when that is one
     * of the unpaid kinds (overdue, under review, ...); otherwise it is pending.
     */
    public static function toLegacy(DocStatus $doc, PaymentStatus $payment, ?InvoiceStatus $current = null): InvoiceStatus
    {
        if ($doc === DocStatus::Void) {
            return InvoiceStatus::Cancelled;
        }

        return match (true) {
            $doc === DocStatus::Draft => InvoiceStatus::Pending,
            $payment === PaymentStatus::Paid => InvoiceStatus::Paid,
            $payment === PaymentStatus::Partial => InvoiceStatus::PartiallyPaid,
            default => self::unpaidStatus($current),
        };
    }

    private static function unpaidStatus(?InvoiceStatus $current): InvoiceStatus
    {
        $keep = [InvoiceStatus::Overdue, InvoiceStatus::UnderReview, InvoiceStatus::PaymentRejected];

        return in_array($current, $keep, true) ? $current : InvoiceStatus::Pending;
    }
}
