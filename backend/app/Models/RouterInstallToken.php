<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single-use, short-lived token that unlocks a router's install.rsc. Not
 * tenant-scoped: the public installer endpoint resolves it before any
 * workspace is known; ownership is carried by its router.
 *
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $used_at
 * @property string|null $encrypted_key
 */
class RouterInstallToken extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'router_id',
        'token_hash',
        'encrypted_key',
        'expires_at',
        'used_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'token_hash',
        'encrypted_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Router, $this> */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class)->withoutGlobalScopes();
    }
}
