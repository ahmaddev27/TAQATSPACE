<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UnbilledChargeSource;
use App\Enums\UnbilledChargeStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\UnbilledChargeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a subscriber owes but has not been invoiced for yet. `amount` is
 * always computed by the service from `qty * unit_price`.
 *
 * @property string $workspace_id
 * @property string $customer_id
 * @property UnbilledChargeSource $source
 * @property UnbilledChargeStatus $status
 */
class UnbilledCharge extends Model
{
    /** @use HasFactory<UnbilledChargeFactory> */
    use BelongsToWorkspace, HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'customer_id',
        'subscription_id',
        'catalog_item_id',
        'description',
        'qty',
        'unit_price',
        'amount',
        'source',
        'source_ref',
        'status',
        'invoice_line_id',
        'occurred_at',
        'created_by',
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
            'source' => UnbilledChargeSource::class,
            'status' => UnbilledChargeStatus::class,
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<CatalogItem, $this> */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function isOpen(): bool
    {
        return $this->status === UnbilledChargeStatus::Open;
    }
}
