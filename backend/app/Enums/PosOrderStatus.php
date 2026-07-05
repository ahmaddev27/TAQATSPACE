<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Lifecycle of a POS order.
 */
enum PosOrderStatus: string
{
    use HasValues;

    /** Placed but not yet settled (a freelancer's request, or an open tab). */
    case Pending = 'pending';

    /** Fully paid — stock has been consumed. */
    case Paid = 'paid';

    /** Voided before payment; never consumes stock. */
    case Cancelled = 'cancelled';
}
