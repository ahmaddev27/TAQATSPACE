<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Where an unbilled charge came from. */
enum UnbilledChargeSource: string
{
    use HasValues;

    case Pos = 'pos';
    case Import = 'import';
    case AfterHours = 'after_hours';
    case Overage = 'overage';
    case Manual = 'manual';
}
