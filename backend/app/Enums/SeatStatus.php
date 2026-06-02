<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum SeatStatus: string
{
    use HasValues;

    case Available = 'available';
    case Occupied = 'occupied';
    case Reserved = 'reserved';
    case Maintenance = 'maintenance';
}
