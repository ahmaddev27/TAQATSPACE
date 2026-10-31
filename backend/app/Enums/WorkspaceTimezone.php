<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** The sixteen IANA time zones a workspace may operate in (SRS NFR-I18N). */
enum WorkspaceTimezone: string
{
    use HasValues;

    case Gaza = 'Asia/Gaza';
    case Jerusalem = 'Asia/Jerusalem';
    case Amman = 'Asia/Amman';
    case Beirut = 'Asia/Beirut';
    case Damascus = 'Asia/Damascus';
    case Baghdad = 'Asia/Baghdad';
    case Riyadh = 'Asia/Riyadh';
    case Kuwait = 'Asia/Kuwait';
    case Qatar = 'Asia/Qatar';
    case Dubai = 'Asia/Dubai';
    case Cairo = 'Africa/Cairo';
    case Tripoli = 'Africa/Tripoli';
    case Tunis = 'Africa/Tunis';
    case Algiers = 'Africa/Algiers';
    case Casablanca = 'Africa/Casablanca';
    case Utc = 'UTC';
}
