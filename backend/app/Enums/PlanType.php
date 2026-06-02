<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PlanType: string
{
    use HasValues;

    case Monthly = 'monthly';
    case Daily = 'daily';
    case Custom = 'custom';
}
