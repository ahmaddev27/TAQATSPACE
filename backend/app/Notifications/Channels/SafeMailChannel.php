<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\Demo\DemoMarker;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A `mail` notification channel that never lets an email failure break delivery.
 *
 * Every notification fans out to its channels (database + mail, plus FCM push via
 * a listener) inside a single queued job. A mail transport error — e.g. an
 * unverified SMTP sender, or the provider being briefly unreachable — would
 * otherwise throw, fail the whole job, and on retry re-run every channel: the
 * in-app notification would be dropped and then duplicated.
 *
 * Here mail is best-effort: failures are logged and swallowed so the database
 * (in-app) and push channels always succeed exactly once. This channel is wired
 * in as the `mail` driver in {@see AppServiceProvider}, so every
 * existing notification benefits without changing its `via()`.
 */
class SafeMailChannel extends MailChannel
{
    /**
     * @param  mixed  $notifiable
     * @return mixed the SentMessage on success, or null when delivery failed
     */
    public function send($notifiable, Notification $notification)
    {
        // Demo mailboxes (accounts, or an invitation addressed to one) are never
        // contacted; a demo account still gets its in-app notification.
        if ($this->addressesDemoMailbox($notifiable, $notification)) {
            return null;
        }

        try {
            return parent::send($notifiable, $notification);
        } catch (Throwable $e) {
            Log::warning('Notification email delivery failed (suppressed)', [
                'notification' => $notification::class,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function addressesDemoMailbox(mixed $notifiable, Notification $notification): bool
    {
        if ($notifiable instanceof User && $notifiable->isDemo()) {
            return true;
        }

        $route = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('mail', $notification)
            : null;
        $addresses = is_array($route) ? array_merge(array_keys($route), array_values($route)) : [$route];

        return collect($addresses)->contains(
            fn ($address): bool => is_string($address) && DemoMarker::isDemoEmail($address),
        );
    }
}
