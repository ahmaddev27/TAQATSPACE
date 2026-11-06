<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One customer's wallet in one workspace. Its row is the lock every wallet
 * movement takes, and `balance` is the running SUM of the wallet's entries,
 * moved only by WalletLedger in the same transaction as the entry.
 *
 * @property string $workspace_id
 * @property string $customer_id
 * @property string $balance
 */
class CustomerWallet extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'customer_id',
        'balance',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }
}
