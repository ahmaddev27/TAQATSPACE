<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HourLedgerReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only: a correction is a new compensating entry, never an edit.
 */
class HourLedgerEntry extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'hour_package_purchase_id', 'workspace_id', 'minutes', 'reason',
        'rate', 'currency', 'source_type', 'source_id', 'note', 'created_by',
    ];

    protected static function booted(): void
    {
        $refuse = static function (): never {
            throw new LogicException('The hour ledger is append-only.');
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
            'minutes' => 'integer',
            'reason' => HourLedgerReason::class,
            'rate' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
