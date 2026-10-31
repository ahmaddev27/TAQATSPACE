<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Kinds of change that need a super admin's approval. Each case is served by one
 * {@see \App\Contracts\ApprovalHandler}; later tasks add their own cases.
 */
enum ApprovalType: string
{
    use HasValues;

    case Capacity = 'capacity';
    case WorkingHours = 'working_hours';
}
