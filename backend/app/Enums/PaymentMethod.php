<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * How a POS order was paid (manual tracking — no payment gateway).
 */
enum PaymentMethod: string
{
    use HasValues;

    case Cash = 'cash';
    case Transfer = 'transfer';
}
