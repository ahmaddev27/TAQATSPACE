<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HourPurchaseStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HourPackagePurchase extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'hour_package_id', 'hours', 'price', 'currency', 'status',
        'activated_at', 'activated_by', 'expires_at',
        'payment_method', 'receipt_path', 'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hours' => 'integer',
            'price' => 'decimal:2',
            'status' => HourPurchaseStatus::class,
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<HourPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(HourPackage::class, 'hour_package_id');
    }

    /** @return HasMany<HourLedgerEntry, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(HourLedgerEntry::class);
    }
}
