<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RouterCommandStatus;
use App\Enums\RouterCommandVerb;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\RouterCommandFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One platform-to-router command. The payload is encrypted at rest because
 * it can carry hotspot passwords and router keys.
 *
 * @property string $workspace_id
 * @property string $router_id
 * @property RouterCommandVerb $verb
 * @property array<string, mixed> $payload
 * @property RouterCommandStatus $status
 */
class RouterCommand extends Model
{
    /** @use HasFactory<RouterCommandFactory> */
    use BelongsToWorkspace, HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'router_id',
        'verb',
        'payload',
        'status',
        'attempts',
        'sync_cycles',
        'delivered_at',
        'acknowledged_at',
        'expires_at',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verb' => RouterCommandVerb::class,
            'payload' => 'encrypted:array',
            'status' => RouterCommandStatus::class,
            'attempts' => 'integer',
            'sync_cycles' => 'integer',
            'delivered_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Router, $this> */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class)->withoutGlobalScopes();
    }
}
