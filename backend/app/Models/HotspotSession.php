<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SessionEndReason;
use App\Enums\SessionSource;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One continuous connection of a device to a router. High volume (BIGINT
 * key); built in bulk by SessionBuilder and never pruned (accounting data).
 *
 * @property int $id
 * @property string $router_id
 * @property string $workspace_id
 * @property int $device_id
 * @property string|null $subscriber_id
 * @property CarbonInterface $started_at
 * @property CarbonInterface|null $ended_at
 * @property CarbonInterface $last_seen_at
 * @property CarbonInterface|null $last_snapshot_at
 * @property int $missed_snapshots
 * @property SessionEndReason|null $end_reason
 * @property int $bytes_in
 * @property int $bytes_out
 * @property SessionSource $source
 */
class HotspotSession extends Model
{
    use BelongsToWorkspace;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'router_id',
        'workspace_id',
        'device_id',
        'subscriber_id',
        'started_at',
        'ended_at',
        'last_seen_at',
        'last_snapshot_at',
        'missed_snapshots',
        'end_reason',
        'bytes_in',
        'bytes_out',
        'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_snapshot_at' => 'datetime',
            'missed_snapshots' => 'integer',
            'bytes_in' => 'integer',
            'bytes_out' => 'integer',
            'end_reason' => SessionEndReason::class,
            'source' => SessionSource::class,
        ];
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    public function durationSeconds(): ?int
    {
        return $this->ended_at === null ? null : $this->ended_at->getTimestamp() - $this->started_at->getTimestamp();
    }

    /** @return BelongsTo<Router, $this> */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class)->withoutGlobalScopes();
    }
}
