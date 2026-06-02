<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewWorkspaceRegistrationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly User $owner,
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
            ->subject('طلب تسجيل مساحة جديد / New workspace registration')
            ->line("صاحب مساحة جديد بانتظار المراجعة: {$this->owner->name} ({$this->owner->email})")
            ->line("A new workspace owner is pending review: {$this->owner->name}.");
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'new_workspace_registration',
            'owner_id' => $this->owner->id,
            'owner_name' => $this->owner->name,
            'owner_email' => $this->owner->email,
        ];
    }
}
