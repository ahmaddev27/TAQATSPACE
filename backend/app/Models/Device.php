<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client device (MAC) seen on a workspace's hotspot. High volume (BIGINT
 * key); written in bulk by DeviceResolver.
 *
 * @property string $workspace_id
 * @property string|null $subscriber_id
 * @property string $mac
 * @property bool $is_randomized_mac
 * @property DeviceStatus $status
 */
class Device extends Model
{
    use BelongsToWorkspace;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'subscriber_id',
        'mac',
        'label',
        'vendor',
        'device_type',
        'is_randomized_mac',
        'status',
        'first_seen_at',
        'last_seen_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_randomized_mac' => 'boolean',
            'status' => DeviceStatus::class,
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subscriber_id');
    }
}
