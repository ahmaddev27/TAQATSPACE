<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * In-app notification for a new realtime (Firestore) chat message. Persisted to
 * the database channel only — the {@see \App\Listeners\PushNotificationToDevices}
 * listener mirrors it to FCM, so the recipient is alerted (bell + native push)
 * even when their chat page is closed.
 *
 * Deliberately NOT queued: the Firestore message is already delivered client-
 * side, so this just raises the alert. Sending synchronously keeps it working
 * even when no queue worker is running, at the cost of a little send latency on
 * the fire-and-forget notify call.
 */
class NewChatMessageNotification extends Notification
{
    public function __construct(
        private readonly User $sender,
        private readonly string $preview,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_chat_message',
            'sender_id' => (string) $this->sender->id,
            'sender_name' => (string) $this->sender->name,
            'preview' => $this->preview,
        ];
    }
}
