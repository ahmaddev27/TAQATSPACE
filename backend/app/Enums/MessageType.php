<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum MessageType: string
{
    use HasValues;

    case Direct = 'direct';
    case Broadcast = 'broadcast';
}
