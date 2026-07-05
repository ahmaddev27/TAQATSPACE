<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Where a POS order originated.
 */
enum PosOrderSource: string
{
    use HasValues;

    /** Rung up by a cashier at the counter (walk-in or verbal order). */
    case Cashier = 'cashier';

    /** Placed by a freelancer from their own dashboard. */
    case Freelancer = 'freelancer';
}
