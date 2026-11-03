<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HotspotAccountStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The hotspot login of one subscription. The password is encrypted at rest.
 *
 * @property string $workspace_id
 * @property string $subscription_id
 * @property string $username
 * @property string $password_enc
 * @property string|null $profile
 * @property HotspotAccountStatus $status
 */
class HotspotAccount extends Model
{
    use BelongsToWorkspace, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'subscription_id',
        'username',
        'password_enc',
        'profile',
        'status',
        'provisioned_at',
    ];

    /** @var list<string> */
    protected $hidden = ['password_enc'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password_enc' => 'encrypted',
            'status' => HotspotAccountStatus::class,
            'provisioned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
