<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Platform-level tenant state, independent of the moderation {@see WorkspaceStatus}. */
enum PlatformStatus: string
{
    use HasValues;

    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';
}
