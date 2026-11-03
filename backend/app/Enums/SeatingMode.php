<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Whether members get an assigned desk or pick any free one. */
enum SeatingMode: string
{
    use HasValues;

    case Fixed = 'fixed';
    case Flexible = 'flexible';
}
