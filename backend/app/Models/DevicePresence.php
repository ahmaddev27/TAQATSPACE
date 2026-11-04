<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A device that is connected to a router's hotspot right now. High volume
 * (BIGINT key); written in bulk by DevicePresenceService.
 *
 * @property string $router_id
 * @property string $workspace_id
 */
class DevicePresence extends Model
{
    use BelongsToWorkspace;

    protected $table = 'device_presence';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'router_id',
        'workspace_id',
        'mac',
        'hotspot_username',
        'ip',
        'uptime_seconds',
        'bytes_in',
        'bytes_out',
        'first_seen_at',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'uptime_seconds' => 'integer',
            'bytes_in' => 'integer',
            'bytes_out' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Router, $this> */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class)->withoutGlobalScopes();
    }
}
