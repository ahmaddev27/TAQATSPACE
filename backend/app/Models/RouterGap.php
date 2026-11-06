<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GapReason;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A period in which a router's data is missing or untrustworthy. Open while
 * `ended_at` is null; `resolved_at` is when the detector closed it.
 *
 * @property string $router_id
 * @property string $workspace_id
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property GapReason $reason
 * @property \Illuminate\Support\Carbon|null $resolved_at
 */
class RouterGap extends Model
{
    use BelongsToWorkspace, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'router_id',
        'workspace_id',
        'started_at',
        'ended_at',
        'reason',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'resolved_at' => 'datetime',
            'reason' => GapReason::class,
        ];
    }

    /** @param Builder<static> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    /** @return BelongsTo<Router, $this> */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }
}
