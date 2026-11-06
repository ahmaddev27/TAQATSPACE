<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** What moved a customer's wallet; each type has a fixed direction, so a stored amount's sign follows from it. */
enum WalletEntryType: string
{
    use HasValues;

    case DepositIn = 'deposit_in';
    case DepositReturn = 'deposit_return';
    case Settlement = 'settlement';
    case Forfeit = 'forfeit';
    case Credit = 'credit';
    case Debit = 'debit';

    /** Whether the entry adds to the balance. */
    public function isIncoming(): bool
    {
        return match ($this) {
            self::DepositIn, self::Credit => true,
            self::DepositReturn, self::Settlement, self::Forfeit, self::Debit => false,
        };
    }
}
