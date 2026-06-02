<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum UserStatus: string
{
    use HasValues;

    case Active = 'active';
    case Suspended = 'suspended';
    case PendingVerification = 'pending_verification';
}
