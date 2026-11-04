<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PaymentStatus: string
{
    use HasValues;

    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
}
