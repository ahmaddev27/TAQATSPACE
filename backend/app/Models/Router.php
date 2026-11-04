<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RouterStatus;
use App\Models\Concerns\BelongsToWorkspace;
use App\Support\Router\RouterKeyHasher;
use Database\Factories\RouterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A MikroTik router that pushes to our API. Only key hashes are stored.
 *
 * @property string $workspace_id
 * @property RouterStatus $status
 * @property string|null $mode
 * @property string|null $script_version
 */
class Router extends Model
{
    /** @use HasFactory<RouterFactory> */
    use BelongsToWorkspace, HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'name',
        'key_hash',
        'previous_key_hash',
        'previous_key_expires_at',
        'serial_number',
        'routeros_version',
        'script_version',
        'mode',
        'status',
        'last_seen_at',
        'last_seq',
        'clock_offset_seconds',
        'boot_id',
        'sessions_boot_id',
        'sessions_boot_since',
    ];

    /** @var list<string> */
    protected $hidden = [
        'key_hash',
        'previous_key_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RouterStatus::class,
            'previous_key_expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'sessions_boot_since' => 'datetime',
            'last_seq' => 'integer',
            'clock_offset_seconds' => 'integer',
        ];
    }

    /** @return HasMany<RouterApiLog, $this> */
    public function apiLogs(): HasMany
    {
        return $this->hasMany(RouterApiLog::class);
    }

    /**
     * Resolve a router from a presented plaintext key: the current key, or the
     * previous one while its grace period lasts. Routers authenticate before
     * any workspace is known, so the tenant scope is bypassed deliberately.
     */
    public static function findByKey(string $plain): ?self
    {
        $hash = RouterKeyHasher::hash($plain);

        return static::query()
            ->withoutWorkspaceScope()
            ->where(fn (Builder $query) => $query
                ->where('key_hash', $hash)
                ->orWhere(fn (Builder $previous) => $previous
                    ->where('previous_key_hash', $hash)
                    ->where('previous_key_expires_at', '>', now())))
            ->first();
    }
}
