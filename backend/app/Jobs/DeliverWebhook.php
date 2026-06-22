<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers ONE webhook to ONE partner, off the request thread.
 *
 * Signs the exact JSON body with HMAC-SHA256 of the partner's webhook secret
 * (header `X-Taqat-Signature: sha256=<hex>`) so the partner can verify
 * authenticity, then records the outcome on the delivery row for audit/retry.
 */
class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry transient network/5xx failures a few times. */
    public int $tries = 4;

    public int $backoff = 60;

    public function __construct(private readonly string $deliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::query()->with('partnerClient')->find($this->deliveryId);

        if ($delivery === null || $delivery->partnerClient === null) {
            return;
        }

        $partner = $delivery->partnerClient;

        if ($partner->webhook_url === null) {
            return;
        }

        // Sign the exact bytes we send so the partner can recompute the HMAC.
        $body = (string) json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = 'sha256='.hash_hmac('sha256', $body, (string) $partner->webhook_secret);

        $delivery->increment('attempts');
        $delivery->forceFill(['last_attempted_at' => now()])->save();

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Taqat-Signature' => $signature,
            ])->withBody($body, 'application/json')
                ->timeout(15)
                ->post($partner->webhook_url);

            $delivery->forceFill([
                'response_code' => $response->status(),
                'status' => $response->successful() ? 'delivered' : 'failed',
            ])->save();

            if (! $response->successful()) {
                // Non-2xx: surface for retry by the queue.
                throw new \RuntimeException("Webhook rejected with HTTP {$response->status()}");
            }
        } catch (Throwable $e) {
            $delivery->forceFill(['status' => 'failed'])->save();

            Log::warning('Webhook delivery failed', [
                'delivery_id' => $delivery->id,
                'partner_id' => $partner->id,
                'event' => $delivery->event,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
