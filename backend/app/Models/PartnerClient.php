<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An external platform authorised to call our partner API (e.g. Academy).
 *
 * The API key is never stored in plaintext: only its SHA-256 hash lives here,
 * and lookups hash the presented key to compare. The webhook secret is used to
 * HMAC-sign outbound webhook bodies so the partner can verify authenticity.
 */
class PartnerClient extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'api_key_hash',
        'webhook_url',
        'webhook_secret',
        'is_active',
        'scopes',
    ];

    /** @var list<string> */
    protected $hidden = [
        'api_key_hash',
        'webhook_secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'scopes' => 'array',
        ];
    }

    /** @return HasMany<WebhookDelivery, $this> */
    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Hash a plaintext API key for storage/comparison. Kept here so the issuing
     * command and the auth middleware share one definition.
     */
    public static function hashKey(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Resolve an active partner from a plaintext API key, or null if none match.
     */
    public static function findByApiKey(string $plain): ?self
    {
        return static::query()
            ->active()
            ->where('api_key_hash', static::hashKey($plain))
            ->first();
    }

    /**
     * @param  Builder<PartnerClient>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
