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
    case Card = 'card';
    case Cheque = 'cheque';
    /** Paid out of the customer's wallet balance; only the wallet settlement creates it. */
    case Wallet = 'wallet';

    /**
     * What a person may pick when recording money by hand (everything but the wallet).
     *
     * @return array<int, string>
     */
    public static function manualValues(): array
    {
        return array_values(array_diff(self::values(), [self::Wallet->value]));
    }
}
