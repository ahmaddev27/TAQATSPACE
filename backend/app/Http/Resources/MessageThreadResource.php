<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One conversation thread from the owner's perspective: the counterpart member,
 * the most recent message exchanged, and how many of their messages are unread.
 *
 * Backed by an array DTO assembled in the service:
 * ['member' => User, 'last_message' => Message, 'unread_count' => int].
 */
class MessageThreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $member */
        $member = $this->resource['member'] ?? null;

        /** @var Message|null $lastMessage */
        $lastMessage = $this->resource['last_message'] ?? null;

        return [
            'member' => [
                'id' => $member?->id,
                'name' => $member?->name,
                'avatar' => $member?->avatar,
                'specialty' => $member?->specialty,
            ],
            'last_message' => $lastMessage === null ? null : [
                'id' => $lastMessage->id,
                'sender_id' => $lastMessage->sender_id,
                'content' => $lastMessage->content,
                'read_at' => $lastMessage->read_at?->toIso8601String(),
                'created_at' => $lastMessage->created_at?->toIso8601String(),
            ],
            'unread_count' => (int) ($this->resource['unread_count'] ?? 0),
        ];
    }
}
