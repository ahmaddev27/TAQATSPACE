<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

/** Rules shared by the requests that move wallet money. */
trait WalletRules
{
    /** `wallet_entries.amount` is DECIMAL(12,2). */
    private const MAX_AMOUNT = 9999999999.99;

    /**
     * @return array<string, mixed>
     */
    protected function walletAmountRules(): array
    {
        return [
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:'.self::MAX_AMOUNT],
            // A client-made key (a UUID is fine) that turns a repeated submit into a no-op.
            'request_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
