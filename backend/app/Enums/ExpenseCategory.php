<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ExpenseCategory: string
{
    use HasValues;

    case Rent = 'rent';
    case Utilities = 'utilities';
    case Salaries = 'salaries';
    case Maintenance = 'maintenance';
    case Supplies = 'supplies';
    case Marketing = 'marketing';
    case Other = 'other';
}
