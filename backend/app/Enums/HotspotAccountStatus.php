<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum HotspotAccountStatus: string
{
    use HasValues;

    case Active = 'active';
    case Suspended = 'suspended';
}
