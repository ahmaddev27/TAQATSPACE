<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One broadcast's message count on one channel, tagged by whether it was sent
 * through the platform (Taqat) accounts or the workspace's own.
 */
class MessageUsage extends Model
{
    use HasUuids;

    protected $table = 'message_usage';

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'channel',
        'source',
        'count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'count' => 'integer',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
