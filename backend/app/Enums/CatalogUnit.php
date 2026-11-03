<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** The unit a catalog item is priced per. */
enum CatalogUnit: string
{
    use HasValues;

    case Piece = 'piece';
    case Hour = 'hour';
    case Day = 'day';
    case Month = 'month';
    case Gb = 'gb';
}
