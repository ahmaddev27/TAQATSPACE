<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Firebase\FirebaseService;
use App\Support\NotificationPushContent;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mirrors every persisted (database-channel) notification to the recipient's
 * FCM device tokens, so the existing in-app notifications also arrive as native
 * push — without modifying any notification class.
 *
 * Listens globally to {@see NotificationSent} (auto-discovered). It is fully
 * graceful: a no-op when Firebase is unconfigured, when the notifiable isn't a
 * user, or when the user has no device tokens; it never throws so notification
 * delivery is never disrupted.
 */
class PushNotificationToDevices
{
    public function __construct(
        private readonly FirebaseService $firebase,
    ) {}

    public function handle(NotificationSent $event): void
    {
        // Only mirror the persisted "database" notification — that's the single
        // canonical in-app record. Mail/broadcast/other channels are ignored so
        // each notification pushes at most once.
        if ($event->channel !== 'database') {
            return;
        }

        if (! $this->firebase->isConfigured()) {
            return;
        }

        $notifiable = $event->notifiable;

        if (! $notifiable instanceof User) {
            return;
        }

        try {
            $tokens = $notifiable->deviceTokens()->pluck('token')->all();

            if ($tokens === []) {
                return;
            }

            $payload = $this->payloadFor($event);

            [$title, $body] = NotificationPushContent::resolve($payload);

            $result = $this->firebase->sendToTokens(
                $tokens,
                $title,
                $body,
                $this->dataFor($payload),
            );

            $this->pruneInvalidTokens($result['invalid_tokens']);
        } catch (Throwable $e) {
            // Never let push failures bubble into notification delivery.
            Log::warning('FCM push from NotificationSent failed.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Extract the array payload that was stored for the database notification.
     *
     * @return array<string, mixed>
     */
    private function payloadFor(NotificationSent $event): array
    {
        $notification = $event->notification;

        if (method_exists($notification, 'toArray')) {
            /** @var array<string, mixed> $payload */
            $payload = $notification->toArray($event->notifiable);

            return $payload;
        }

        return [];
    }

    /**
     * The FCM data payload: the stored notification fields, plus the
     * notification id so the client can deep-link / de-duplicate against the
     * in-app feed. Values are coerced to strings by the service.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, scalar|null>
     */
    private function dataFor(array $payload): array
    {
        $data = [];

        foreach ($payload as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Delete device tokens FCM reported as unregistered/invalid.
     *
     * @param  list<string>  $tokens
     */
    private function pruneInvalidTokens(array $tokens): void
    {
        if ($tokens === []) {
            return;
        }

        DeviceToken::whereIn('token', $tokens)->delete();
    }
}
