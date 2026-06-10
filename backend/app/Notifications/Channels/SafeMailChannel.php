<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

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
 * in as the `mail` driver in {@see \App\Providers\AppServiceProvider}, so every
 * existing notification benefits without changing its `via()`.
 */
class SafeMailChannel extends MailChannel
{
    /**
     * @param  mixed  $notifiable
     * @return mixed  the SentMessage on success, or null when delivery failed
     */
    public function send($notifiable, Notification $notification)
    {
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
}
