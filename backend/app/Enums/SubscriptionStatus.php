<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum SubscriptionStatus: string
{
    use HasValues;

    case Active = 'active';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Pending = 'pending';
    case Suspended = 'suspended';
}
