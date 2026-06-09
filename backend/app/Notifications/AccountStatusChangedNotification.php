<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\UserStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a user when an admin suspends or reactivates their account, so the
 * change never happens silently. Sent on the suspend/reactivate transitions.
 */
class AccountStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly UserStatus $status,
    ) {}

    /**
     * @param  object  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @param  object  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        [$subject, $arabic, $english] = $this->status === UserStatus::Suspended
            ? [
                'تم تعليق حسابك / Account suspended',
                'تم تعليق حسابك من قِبل الإدارة. للاستفسار يُرجى التواصل معنا.',
                'Your account has been suspended by the administration. Please contact us for details.',
            ]
            : [
                'تمت إعادة تفعيل حسابك / Account reactivated',
                'تمت إعادة تفعيل حسابك. يمكنك تسجيل الدخول واستخدام المنصّة الآن.',
                'Your account has been reactivated. You can sign in and use the platform again.',
            ];

        return (new MailMessage)
            ->subject($subject)
            ->line($arabic)
            ->line($english);
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'account_status_changed',
            'status' => $this->status->value,
        ];
    }
}
