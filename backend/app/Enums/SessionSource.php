<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum SessionSource: string
{
    use HasValues;

    case Event = 'event';
    case Snapshot = 'snapshot';
}
