<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum BookingStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
