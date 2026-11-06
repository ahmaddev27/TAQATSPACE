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
 * customer's entries. Append-only: a mistake is corrected by a new entry
 * (a reversal, or voiding the document that wrote it).
 *
 * @property string $workspace_id
 * @property string|null $customer_id
 * @property WalletEntryType $type
 */
class WalletEntry extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * Whether a later entry reversed this one, set by WalletRepository for a
     * response. A plain property, not an attribute: it is never saved.
     */
    public ?bool $reversed = null;

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

    /** Whether the owner may undo this entry by hand (a wrong deposit or forfeit) and has not yet. */
    public function isReversible(): bool
    {
        return $this->type->reversal() !== null && $this->reversed !== true;
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
