<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum GapReason: string
{
    use HasValues;

    /** The router stopped syncing for longer than the threshold. */
    case NoSync = 'no_sync';

    /** The router came back with a different boot id. */
    case Reboot = 'reboot';

    /** The router's clock moved by more than the tolerated jump. */
    case ClockJump = 'clock_jump';
}
