<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * FULFILLMENT status of a POS order — an axis independent of payment (which is
 * tracked by `paid_at`). An order flows new -> preparing -> ready -> completed,
 * and can be cancelled (while still open and unpaid) or refunded (after payment).
 */
enum PosOrderStatus: string
{
    use HasValues;

    /** Just placed (a walk-in ticket, a freelancer's request, or an open tab). */
    case New = 'new';

    /** Being made by the barista/kitchen. */
    case Preparing = 'preparing';

    /** Made and waiting for pickup/hand-off. */
    case Ready = 'ready';

    /** Handed to the customer — the fulfillment flow is done. */
    case Completed = 'completed';

    /** Voided before payment; never consumes stock. */
    case Cancelled = 'cancelled';

    /** Reversed after payment; stock is restored and the payment negated. */
    case Refunded = 'refunded';

    /** Whether the order is still in the active fulfillment queue. */
    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::Preparing, self::Ready], true);
    }

    /**
     * The forward fulfillment path, in order, used to validate transitions.
     *
     * @return array<int, self>
     */
    public static function fulfillmentFlow(): array
    {
        return [self::New, self::Preparing, self::Ready, self::Completed];
    }
}
