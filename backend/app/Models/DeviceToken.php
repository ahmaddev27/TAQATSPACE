<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An FCM registration token for a single device belonging to a user. Used to
 * fan out push notifications; pruned automatically when FCM reports the token
 * unregistered.
 *
 * @property string $user_id
 * @property string $token
 * @property string|null $platform
 */
class DeviceToken extends Model
{
    /** @use HasFactory<\Database\Factories\DeviceTokenFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'token',
        'platform',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
