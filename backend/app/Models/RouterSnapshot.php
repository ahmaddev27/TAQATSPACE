<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A full-state snapshot of a router, sent in chunks. Complete once every
 * chunk has arrived; reassembly is left to the consumer.
 */
class RouterSnapshot extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'router_id',
        'snapshot_id',
        'total_chunks',
        'received_chunks',
        'taken_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_chunks' => 'integer',
            'received_chunks' => 'integer',
            'taken_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    /** @return BelongsTo<Router, $this> */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class)->withoutGlobalScopes();
    }

    /** @return HasMany<RouterSnapshotChunk, $this> */
    public function chunks(): HasMany
    {
        return $this->hasMany(RouterSnapshotChunk::class)->orderBy('chunk_index');
    }
}
