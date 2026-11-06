<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A numbered document for money paid back to a customer. It is voided, never
 * deleted; voiding puts the money back on the wallet through a reversing entry.
 *
 * @property string $workspace_id
 * @property PaymentMethod $method
 */
class PaymentVoucher extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'customer_id',
        'number',
        'method',
        'amount',
        'reason',
        'reference',
        'wallet_entry_id',
        'paid_at',
        'created_by',
        'voided_at',
        'void_reason',
    ];

    /** What the numbered document says was paid, to whom and how; it never changes once issued. */
    private const FROZEN = ['workspace_id', 'customer_id', 'number', 'method', 'amount'];

    protected static function booted(): void
    {
        static::updating(static function (PaymentVoucher $voucher): void {
            if ($voucher->isDirty(self::FROZEN)) {
                throw new LogicException('A payment voucher is voided, never edited.');
            }
        });

        static::deleting(static function (): never {
            throw new LogicException('A payment voucher is voided, never deleted.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
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

    /** @return BelongsTo<WalletEntry, $this> */
    public function walletEntry(): BelongsTo
    {
        return $this->belongsTo(WalletEntry::class);
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
