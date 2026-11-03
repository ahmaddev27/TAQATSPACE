<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Whether a catalog category groups things the workspace sells or things it spends on. */
enum CatalogKind: string
{
    use HasValues;

    case Sales = 'sales';
    case Expense = 'expense';
}
