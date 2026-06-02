<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum SeatType: string
{
    use HasValues;

    case Fixed = 'fixed';
    case Flexible = 'flexible';
    case PrivateOffice = 'private_office';
}
