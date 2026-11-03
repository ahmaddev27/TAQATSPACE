<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum RouterStatus: string
{
    use HasValues;

    /** Created, waiting for the first sync. */
    case Pending = 'pending';
    case Online = 'online';
    case Offline = 'offline';

    /** Bound to a serial number and called from a different device. */
    case Locked = 'locked';
    case Disabled = 'disabled';
}
