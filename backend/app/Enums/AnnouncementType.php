<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum AnnouncementType: string
{
    use HasValues;

    case Offer = 'offer';
    case Info = 'info';
    case Alert = 'alert';
    case System = 'system';
}
