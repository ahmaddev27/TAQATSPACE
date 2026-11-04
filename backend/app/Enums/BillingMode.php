<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** When a subscription is billed relative to its period. */
enum BillingMode: string
{
    use HasValues;

    /** Billed before the period starts. */
    case Prepaid = 'prepaid';

    /** Billed after the period (the behavior every legacy subscription has). */
    case Postpaid = 'postpaid';
}
