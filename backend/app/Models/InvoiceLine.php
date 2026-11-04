<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\InvoiceLineObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One billed line of an invoice. `amount` is always `qty * unit_price`, set by
 * the lifecycle service; lines are frozen together with their invoice.
 */
#[ObservedBy([InvoiceLineObserver::class])]
class InvoiceLine extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'invoice_id',
        'description',
        'qty',
        'unit_price',
        'amount',
        'unbilled_charge_id',
        'catalog_item_id',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<UnbilledCharge, $this> */
    public function unbilledCharge(): BelongsTo
    {
        return $this->belongsTo(UnbilledCharge::class)->withoutGlobalScopes();
    }
}
