<?php

declare(strict_types=1);

namespace App\Testing\Hotspot;

use App\Enums\Concerns\HasValues;

enum SimulationScenario: string
{
    use HasValues;

    case Normal = 'normal';
    case Outage = 'outage';
    case Duplicates = 'duplicates';
    case ClockDrift = 'clock-drift';
    case RebootStorm = 'reboot-storm';
}
