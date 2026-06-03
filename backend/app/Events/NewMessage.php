<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to a single member's private channel when an owner sends them a
 * direct message. Only safe, presentation-ready fields are exposed.
 */
class NewMessage implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Message $message,
        public readonly string $receiverId,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->receiverId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.new';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'sender_id' => $this->message->sender_id,
            'workspace_id' => $this->message->workspace_id,
            'type' => $this->message->type->value,
            'content' => $this->message->content,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
