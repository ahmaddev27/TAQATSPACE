<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalletEntryType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line of a customer's wallet ledger. `amount` is signed (positive adds to
 * the balance, negative takes from it) and the balance is always the SUM of a
 * customer's entries. Append-only: a mistake is corrected by a new entry.
 *
 * @property string $workspace_id
 * @property string|null $customer_id
 * @property WalletEntryType $type
 */
class WalletEntry extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'customer_id',
        'type',
        'amount',
        'method',
        'reason',
        'related_type',
        'related_id',
        'request_key',
        'created_by',
    ];

    protected static function booted(): void
    {
        $refuse = static function (): never {
            throw new LogicException('The wallet ledger is append-only.');
        };

        static::updating($refuse);
        static::deleting($refuse);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WalletEntryType::class,
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
