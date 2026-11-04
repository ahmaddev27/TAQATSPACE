<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** What a station physically is, independent of how it is sold (SeatType). */
enum SeatKind: string
{
    use HasValues;

    case Desk = 'desk';
    case Room = 'room';
    case Booth = 'booth';
}
