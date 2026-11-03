<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum HourLedgerReason: string
{
    use HasValues;

    case Credit = 'credit';
    case Usage = 'usage';
    case Adjustment = 'adjustment';
    case Refund = 'refund';
    case Expiry = 'expiry';
}
