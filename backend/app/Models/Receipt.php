<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChequeStatus;
use App\Enums\PaymentMethod;
use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

/**
 * Money received from a customer, numbered per workspace. It may be allocated
 * across several invoices; whatever is not allocated stays as credit on account.
 *
 * @property string $workspace_id
 * @property PaymentMethod $method
 * @property ChequeStatus|null $cheque_status
 */
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'customer_id',
        'number',
        'method',
        'amount',
        'received_at',
        'collected_at',
        'reference',
        'cheque_status',
        'receipt_path',
        'receipt_disk',
        'voided_at',
        'void_reason',
        'created_by',
        'legacy_payment_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'cheque_status' => ChequeStatus::class,
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
            'collected_at' => 'datetime',
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

    /** @return HasMany<ReceiptAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class);
    }

    /** A short-lived signed link to the private proof file, or null when there is none. */
    public function fileUrl(): ?string
    {
        if ($this->receipt_path === null) {
            return null;
        }

        return URL::temporarySignedRoute('finance.receipts.file', now()->addMinutes(10), ['receipt' => $this->id]);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function isCheque(): bool
    {
        return $this->method === PaymentMethod::Cheque;
    }

    /** Whether its allocations count as money paid on the invoices (a cheque only once cleared). */
    public function isCollected(): bool
    {
        if ($this->isVoided()) {
            return false;
        }

        return ! $this->isCheque() || $this->cheque_status === ChequeStatus::Cleared;
    }

    /** Whether its allocations still hold part of the invoices' balance (a bounced cheque lets go). */
    public function holdsBalance(): bool
    {
        return ! $this->isVoided() && $this->cheque_status !== ChequeStatus::Bounced;
    }
}
