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

            $data = $this->dataFor($payload);
            // Deep-link the native push to the relevant in-app page; the service
            // worker reads `data.link` when the notification is clicked.
            $data['link'] = $this->linkFor((string) ($payload['type'] ?? ''), $notifiable);

            $result = $this->firebase->sendToTokens($tokens, $title, $body, $data);

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
            // FCM HTTP v1 requires every data value to be a (non-null) string;
            // coerce scalars and drop nulls so a single int/bool field can't
            // reject the whole push.
            if (is_scalar($value)) {
                $data[$key] = (string) $value;
            }
        }

        return $data;
    }

    /**
     * A locale-prefixed in-app path for the push to open, scoped to the
     * recipient's role + the notification type (mirrors the SPA's notifHref).
     * Defaults to the role's dashboard root when there's no specific target.
     */
    private function linkFor(string $type, User $user): string
    {
        $locale = (string) config('app.locale', 'ar');

        $base = match (true) {
            $user->isOwner() => '/owner',
            $user->isAdmin() => '/admin',
            default => '/freelancer',
        };

        $section = match ($type) {
            'booking_approved', 'booking_rejected', 'new_booking_request' => $user->isOwner() ? '/requests' : '',
            'invoice_created', 'invoice_overdue', 'invoice_paid', 'invoice_reminder', 'invoice_receipt_submitted' => '/invoices',
            'subscription_expiring' => $user->isFreelancer() ? '/subscription' : '/subscriptions',
            'seat_assigned' => $user->isOwner() ? '/seats' : '',
            'new_announcement' => $user->isOwner() ? '/announcements' : '',
            'new_review' => $user->isOwner() ? '/reports' : '',
            'new_contact_message' => $user->isAdmin() ? '/messages' : '',
            'new_message' => $user->isOwner() ? '/messages' : '/chat',
            'new_chat_message' => '/chat',
            default => '',
        };

        return "/{$locale}{$base}{$section}";
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
