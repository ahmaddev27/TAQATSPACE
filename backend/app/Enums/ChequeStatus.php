<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Where a received cheque stands: only a cleared one counts as money collected. */
enum ChequeStatus: string
{
    use HasValues;

    case Received = 'received';
    case Deposited = 'deposited';
    case Cleared = 'cleared';
    case Bounced = 'bounced';

    /** @return array<int, self> */
    public function next(): array
    {
        return match ($this) {
            self::Received => [self::Deposited, self::Bounced],
            self::Deposited => [self::Cleared, self::Bounced],
            // A cheque can come back after it cleared; the money is then reversed.
            self::Cleared => [self::Bounced],
            self::Bounced => [],
        };
    }

    public function canMoveTo(self $target): bool
    {
        return in_array($target, $this->next(), true);
    }
}
