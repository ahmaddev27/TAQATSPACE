<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Platform-level catalog entry (no workspace). Archiving flips is_active so
 * existing purchases, which freeze hours and price, keep working.
 */
class HourPackage extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'code', 'name_ar', 'name_en', 'hours', 'price', 'currency',
        'validity_days', 'is_active', 'sort_order',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['is_active' => true, 'sort_order' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hours' => 'integer',
            'price' => 'decimal:2',
            'validity_days' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<HourPackagePurchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(HourPackagePurchase::class);
    }
}
