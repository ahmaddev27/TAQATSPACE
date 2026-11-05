<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A numbered correction of an issued invoice. It never edits the invoice; it
 * lowers what the customer owes and, when part of that was already paid,
 * records the amount owed back as `refund_amount`.
 *
 * @property string $workspace_id
 * @property string $invoice_id
 */
class CreditNote extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'invoice_id',
        'number',
        'amount',
        'refund_amount',
        'reason',
        'issued_by',
        'issued_at',
        'voided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
