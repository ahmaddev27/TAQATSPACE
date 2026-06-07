<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ResourceType: string
{
    use HasValues;

    case MeetingRoom = 'meeting_room';
    case PrivateOffice = 'private_office';
    case Equipment = 'equipment';
    case Parking = 'parking';
    case Other = 'other';
}
