<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SeatType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatTypePrice extends Model
{
    /** @use HasFactory<\Database\Factories\SeatTypePriceFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'type',
        'price_monthly',
        'price_daily',
        'capacity',
        'enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SeatType::class,
            'price_monthly' => 'decimal:2',
            'price_daily' => 'decimal:2',
            'capacity' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
