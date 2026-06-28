<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum InvoiceStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case UnderReview = 'under_review';
    case PaymentRejected = 'payment_rejected';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';
}
