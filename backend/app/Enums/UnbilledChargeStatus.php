<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Lifecycle of an unbilled charge: open until invoiced or voided. */
enum UnbilledChargeStatus: string
{
    use HasValues;

    case Open = 'open';
    case Invoiced = 'invoiced';
    case Voided = 'voided';
}
