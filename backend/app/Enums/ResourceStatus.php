<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ResourceStatus: string
{
    use HasValues;

    case Available = 'available';
    case InUse = 'in_use';
    case Maintenance = 'maintenance';
    case Unavailable = 'unavailable';
}
