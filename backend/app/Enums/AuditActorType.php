<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum AuditActorType: string
{
    use HasValues;

    case User = 'user';
    case System = 'system';
    case Router = 'router';
}
