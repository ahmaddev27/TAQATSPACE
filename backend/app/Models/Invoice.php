<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Invoice extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'subscription_id',
        'amount',
        'due_date',
        'paid_at',
        'status',
        'invoice_number',
        'invoice_pdf_path',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
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
     * Invoices belonging to a workspace, resolved through the subscription.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeForWorkspace(Builder $query, string $workspaceId): void
    {
        $query->whereHas('subscription', static function (Builder $subscription) use ($workspaceId): void {
            $subscription->where('workspace_id', $workspaceId);
        });
    }

    /**
     * Invoices belonging to a member, resolved through the subscription.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeForMember(Builder $query, string $memberId): void
    {
        $query->whereHas('subscription', static function (Builder $subscription) use ($memberId): void {
            $subscription->where('member_id', $memberId);
        });
    }

    // ---- Helpers ----

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
