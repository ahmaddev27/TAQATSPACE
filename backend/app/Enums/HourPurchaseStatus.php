<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum HourPurchaseStatus: string
{
    use HasValues;

    case Requested = 'requested';
    case Active = 'active';
    case Exhausted = 'exhausted';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
