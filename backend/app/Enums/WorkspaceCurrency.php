<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** The eight currencies a workspace may price in (SRS NFR-I18N). */
enum WorkspaceCurrency: string
{
    use HasValues;

    case ILS = 'ILS';
    case USD = 'USD';
    case EUR = 'EUR';
    case JOD = 'JOD';
    case EGP = 'EGP';
    case SAR = 'SAR';
    case AED = 'AED';
    case KWD = 'KWD';
}
