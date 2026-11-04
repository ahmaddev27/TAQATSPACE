<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RouterApiLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One request a router made to the API. High volume (BIGINT key) and not
 * tenant-scoped; read through its router.
 */
class RouterApiLog extends Model
{
    /** @use HasFactory<RouterApiLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'router_id',
        'path',
        'response_code',
        'duration_ms',
        'body_size',
        'error_message',
        'request_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_code' => 'integer',
            'duration_ms' => 'integer',
            'body_size' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Router, $this> */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class)->withoutGlobalScopes();
    }
}
