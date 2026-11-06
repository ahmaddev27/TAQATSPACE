<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Observers\InvoiceImmutabilityObserver;
use App\Support\Finance\InvoiceMoney;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property DocStatus $doc_status
 * @property PaymentStatus $payment_status
 * @property string|null $workspace_id
 * @property string|null $customer_id
 */
#[ObservedBy([InvoiceImmutabilityObserver::class])]
class Invoice extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'subscription_id',
        'workspace_id',
        'customer_id',
        'doc_status',
        'payment_status',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'period_start',
        'period_end',
        'issued_at',
        'voided_at',
        'void_reason',
        'amount',
        'amount_paid',
        'credited_amount',
        'due_date',
        'paid_at',
        'status',
        'invoice_number',
        'invoice_pdf_path',
        'receipt_path',
        'receipt_rejected_reason',
        'receipt_reviewed_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'doc_status' => DocStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'receipt_reviewed_at' => 'datetime',
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'credited_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    /** @return HasMany<CreditNote, $this> */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class)->orderBy('issued_at');
    }

    /** @return HasMany<InvoicePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderBy('paid_at');
    }

    // ---- Query scopes (T041) ----

    /**
     * @param  Builder<Invoice>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', InvoiceStatus::Pending->value);
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public function scopePaid(Builder $query): void
    {
        $query->where('status', InvoiceStatus::Paid->value);
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', InvoiceStatus::Overdue->value);
    }

    /**
     * Invoices belonging to a workspace: stamped with it directly, or (legacy
     * rows written before the stamp existed) resolved through the subscription.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeForWorkspace(Builder $query, string $workspaceId): void
    {
        $query->where(static function (Builder $inner) use ($workspaceId): void {
            $inner->where('workspace_id', $workspaceId)
                ->orWhereHas('subscription', static function (Builder $subscription) use ($workspaceId): void {
                    $subscription->where('workspace_id', $workspaceId);
                });
        });
    }

    /**
     * Invoices addressed to a member. Drafts are internal to the workspace, so
     * the member never sees them.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeForMember(Builder $query, string $memberId): void
    {
        $query->where('doc_status', '!=', DocStatus::Draft->value)
            ->where(static function (Builder $inner) use ($memberId): void {
                $inner->where('customer_id', $memberId)
                    ->orWhereHas('subscription', static function (Builder $subscription) use ($memberId): void {
                        $subscription->where('member_id', $memberId);
                    });
            });
    }

    /**
     * Everything except unissued drafts: the invoices that count as receivables.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeNotDraft(Builder $query): void
    {
        $query->where('doc_status', '!=', DocStatus::Draft->value);
    }

    /**
     * Invoices that can still be owed money, the same statuses the owner aging
     * report counts. Qualified, so it also works on queries joined to subscriptions.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeReceivable(Builder $query): void
    {
        $query->whereIn($query->qualifyColumn('status'), InvoiceMoney::RECEIVABLE_STATUSES);
    }

    /**
     * Receivables that are late: flagged overdue, or partially paid after the
     * due date. markOverdue only flags pending invoices, so a part-paid one is
     * never flagged however late it is (remindOverdue chases the same pair).
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeOverdueReceivable(Builder $query): void
    {
        $status = $query->qualifyColumn('status');
        $dueDate = $query->qualifyColumn('due_date');

        $query->where(static function (Builder $late) use ($status, $dueDate): void {
            $late->where($status, InvoiceStatus::Overdue->value)
                ->orWhere(static function (Builder $partial) use ($status, $dueDate): void {
                    $partial->where($status, InvoiceStatus::PartiallyPaid->value)
                        ->whereDate($dueDate, '<', Carbon::today()->toDateString());
                });
        });
    }

    // ---- Helpers ----

    public function isDraft(): bool
    {
        return $this->doc_status === DocStatus::Draft;
    }

    /** Whether the invoice belongs to the workspace (direct stamp, else via its subscription). */
    public function belongsToWorkspace(string $workspaceId): bool
    {
        if ($this->workspace_id !== null) {
            return $this->workspace_id === $workspaceId;
        }

        $this->loadMissing('subscription');

        return $this->subscription?->workspace_id === $workspaceId;
    }

    /**
     * An invoice is overdue when its due date has passed and it is unpaid.
     */
    public function isOverdue(): bool
    {
        if ($this->status === InvoiceStatus::Paid) {
            return false;
        }

        return $this->due_date !== null
            && $this->due_date->lt(Carbon::today());
    }
}
