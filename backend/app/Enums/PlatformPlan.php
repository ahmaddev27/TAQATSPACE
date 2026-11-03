<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Administrative label only; there are no paid SaaS plans. */
enum PlatformPlan: string
{
    use HasValues;

    case Standard = 'standard';
}
