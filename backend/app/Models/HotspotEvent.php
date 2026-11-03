<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One raw event pushed by a router, timestamped on the server's clock.
 * High volume (BIGINT key) and not tenant-scoped; read through its router.
 */
class HotspotEvent extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'router_id',
        'workspace_id',
        'event_uid',
        'batch_id',
        'type',
        'mac',
        'ip',
        'username',
        'data',
        'occurred_at',
        'received_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Router, $this> */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class)->withoutGlobalScopes();
    }
}
