<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reminds a member that their active subscription is about to expire, so they
 * can renew before losing their seat. Sent by the daily
 * `subscriptions:notify-expiring` command a few days before the end date.
 */
class SubscriptionExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly int $daysLeft,
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
        $name = $this->subscription->workspace?->name ?? '';

        return (new MailMessage)
            ->subject('اشتراكك قارب على الانتهاء / Subscription expiring soon')
            ->line("اشتراكك في \"{$name}\" ينتهي خلال {$this->daysLeft} يوم. يُرجى التجديد للحفاظ على مقعدك.")
            ->line("Your subscription to \"{$name}\" expires in {$this->daysLeft} day(s). Please renew to keep your seat.");
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'subscription_expiring',
            'subscription_id' => $this->subscription->id,
            'workspace_id' => $this->subscription->workspace_id,
            'workspace_name' => $this->subscription->workspace?->name,
            'days_left' => $this->daysLeft,
            'end_date' => $this->subscription->end_date?->toDateString(),
        ];
    }
}
