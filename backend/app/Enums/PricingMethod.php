<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** How a workspace plan is charged. */
enum PricingMethod: string
{
    use HasValues;

    case Monthly = 'monthly';
    case Weekly = 'weekly';
    case Daily = 'daily';
    case Hourly = 'hourly';
    case PerGb = 'per_gb';
    case Bundle = 'bundle';

    /** BR-FIN-04: only time-based or bundled plans can reserve a dedicated seat. */
    public function allowsDedicatedSeat(): bool
    {
        return in_array($this, [self::Monthly, self::Weekly, self::Bundle], true);
    }

    /** Metered plans bill a rate per unit, so they need an allowance and an overage rate. */
    public function isMetered(): bool
    {
        return in_array($this, [self::Hourly, self::PerGb], true);
    }

    public function isBundle(): bool
    {
        return $this === self::Bundle;
    }
}
