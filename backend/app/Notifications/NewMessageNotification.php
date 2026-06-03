<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\MessageType;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Persists an in-app notification (database channel) and emits a realtime
 * broadcast when a member receives a workspace message — direct or broadcast.
 */
class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Message $message,
        public readonly string $workspaceName,
    ) {}

    /**
     * @param  object  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @param  object  $notifiable
     */
    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'type' => 'new_message',
            'message_id' => $this->message->id,
            'message_type' => $this->message->type->value,
            'sender_id' => $this->message->sender_id,
            'workspace_id' => $this->message->workspace_id,
            'workspace_name' => $this->workspaceName,
            'is_broadcast' => $this->message->type === MessageType::Broadcast,
            'preview' => $this->preview($this->message->content),
        ];
    }

    private function preview(string $content): string
    {
        $trimmed = trim($content);

        return mb_strlen($trimmed) > 120
            ? mb_substr($trimmed, 0, 120).'…'
            : $trimmed;
    }
}
