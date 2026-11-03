<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One piece of a router snapshot. Pruned after 7 days. */
class RouterSnapshotChunk extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['router_snapshot_id', 'chunk_index', 'payload'];

    /** @return BelongsTo<RouterSnapshot, $this> */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(RouterSnapshot::class, 'router_snapshot_id');
    }
}
