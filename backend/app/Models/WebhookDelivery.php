<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One outbound webhook delivery attempt record — the audit/retry trail for a
 * single event sent to a partner.
 */
class WebhookDelivery extends Model
{
    /** @use HasFactory<WebhookDeliveryFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'partner_client_id',
        'event',
        'payload',
        'status',
        'attempts',
        'response_code',
        'last_attempted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'last_attempted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PartnerClient, $this> */
    public function partnerClient(): BelongsTo
    {
        return $this->belongsTo(PartnerClient::class);
    }
}
