<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum DeviceStatus: string
{
    use HasValues;

    case Linked = 'linked';
    case Discovered = 'discovered';
    case Blocked = 'blocked';
}
