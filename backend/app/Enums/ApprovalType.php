<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\ApprovalHandler;
use App\Enums\Concerns\HasValues;

/**
 * Kinds of change that need a super admin's approval. Each case is served by one
 * {@see ApprovalHandler}; later tasks add their own cases.
 */
enum ApprovalType: string
{
    use HasValues;

    case Capacity = 'capacity';
    case WorkingHours = 'working_hours';
    case Pricing = 'pricing';
}
