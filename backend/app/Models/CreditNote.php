<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A numbered correction of an issued invoice. It never edits the invoice; it
 * lowers what the customer owes. Any part of it the customer had already paid
 * is owed back; that is derived from the invoice on every read
 * (InvoiceBalanceService::refundDue), never stored, because a later reversal
 * of the payment changes it.
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
