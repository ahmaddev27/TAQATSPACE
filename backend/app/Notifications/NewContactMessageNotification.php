<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the admins a new "Contact us" message arrived, so they can read and
 * reply to it from the inbox.
 */
class NewContactMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ContactMessage $message,
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
        return (new MailMessage)
            ->subject('رسالة تواصل جديدة / New contact message')
            ->line("من / From: {$this->message->name} <{$this->message->email}>")
            ->line("الموضوع / Subject: {$this->message->subject}")
            ->line($this->message->message);
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'new_contact_message',
            'contact_message_id' => $this->message->id,
            'subject' => $this->message->subject,
        ];
    }
}
