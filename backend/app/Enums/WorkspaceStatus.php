<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum WorkspaceStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Rejected = 'rejected';
}
