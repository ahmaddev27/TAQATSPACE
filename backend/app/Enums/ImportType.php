<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** What an import file contains. */
enum ImportType: string
{
    use HasValues;

    case CatalogItems = 'catalog_items';
    case Withdrawals = 'withdrawals';
}
