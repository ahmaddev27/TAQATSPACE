<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MessageType;
use App\Enums\SubscriptionStatus;
use App\Events\NewMessage;
use App\Models\Message;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\NewMessageNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class MessageService
{
    /**
     * Thread summaries for the owner: one entry per member they have exchanged
     * direct messages with, carrying the last message and the count of unread
     * messages that the member sent to the owner.
     *
     * @return Collection<int, array{member: User, last_message: Message, unread_count: int}>
     */
    public function listThreads(Workspace $workspace, string $ownerId): Collection
    {
        $directMessages = Message::query()
            ->forWorkspace($workspace->id)
            ->where('type', MessageType::Direct->value)
            ->where(static function ($query) use ($ownerId): void {
                $query->where('sender_id', $ownerId)->orWhere('receiver_id', $ownerId);
            })
            ->latest()
            ->get();

        $byCounterpart = $directMessages->groupBy(
            static fn (Message $message): string => $message->sender_id === $ownerId
                ? (string) $message->receiver_id
                : (string) $message->sender_id,
        );

        $memberIds = $byCounterpart->keys()->all();

        if ($memberIds === []) {
            return collect();
        }

        $members = User::query()
            ->whereIn('id', $memberIds)
            ->get()
            ->keyBy('id');

        return $byCounterpart
            ->map(function (Collection $thread, string $memberId) use ($members, $ownerId): ?array {
                $member = $members->get($memberId);

                if ($member === null) {
                    return null;
                }

                return [
                    'member' => $member,
                    'last_message' => $thread->first(),
                    'unread_count' => $thread
                        ->where('receiver_id', $ownerId)
                        ->whereNull('read_at')
                        ->count(),
                ];
            })
            ->filter()
            ->sortByDesc(static fn (array $thread): ?Carbon => $thread['last_message']->created_at)
            ->values();
    }

    /**
     * Full direct conversation between the owner and one member, oldest first.
     *
     * @return Collection<int, Message>
     */
    public function thread(Workspace $workspace, string $ownerId, string $memberId): Collection
    {
        return Message::query()
            ->forWorkspace($workspace->id)
            ->threadWith($ownerId, $memberId)
            ->with('sender:id,name,avatar')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Persist a direct message, fire the realtime event, and notify the member.
     */
    public function sendDirect(Workspace $workspace, string $senderId, string $receiverId, string $content): Message
    {
        $message = Message::query()->create([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'workspace_id' => $workspace->id,
            'type' => MessageType::Direct->value,
            'content' => $content,
        ]);

        NewMessage::dispatch($message, $receiverId);

        $receiver = User::query()->find($receiverId);

        if ($receiver !== null) {
            $receiver->notify(new NewMessageNotification($message, $workspace->name));
        }

        return $message;
    }

    /**
     * Persist a single broadcast message (receiver_id = null) and notify every
     * active member of the workspace.
     */
    public function broadcast(Workspace $workspace, string $senderId, string $content): Message
    {
        $message = Message::query()->create([
            'sender_id' => $senderId,
            'receiver_id' => null,
            'workspace_id' => $workspace->id,
            'type' => MessageType::Broadcast->value,
            'content' => $content,
        ]);

        $members = $this->activeMembers($workspace->id);

        if ($members->isNotEmpty()) {
            Notification::send($members, new NewMessageNotification($message, $workspace->name));

            $members->each(static function (User $member) use ($message): void {
                NewMessage::dispatch($message, (string) $member->id);
            });
        }

        return $message;
    }

    /**
     * Mark as read every direct message the given user has received within the
     * conversation with the counterpart. Returns the number of rows updated.
     */
    public function markThreadRead(Workspace $workspace, string $readerId, string $counterpartId): int
    {
        return Message::query()
            ->forWorkspace($workspace->id)
            ->where('type', MessageType::Direct->value)
            ->where('receiver_id', $readerId)
            ->where('sender_id', $counterpartId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Active, non-cancelled members of a workspace (deduplicated users).
     *
     * @return Collection<int, User>
     */
    public function activeMembers(string $workspaceId): Collection
    {
        $memberIds = Subscription::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', SubscriptionStatus::Active->value)
            ->distinct()
            ->pluck('member_id');

        if ($memberIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $memberIds->all())
            ->get();
    }

    /**
     * Whether a user is an active member of the given workspace.
     */
    public function isActiveMember(string $workspaceId, string $userId): bool
    {
        return Subscription::query()
            ->where('workspace_id', $workspaceId)
            ->where('member_id', $userId)
            ->where('status', SubscriptionStatus::Active->value)
            ->exists();
    }

    /**
     * The workspace a member is actively subscribed to (most recent active
     * subscription wins when more than one exists).
     */
    public function memberActiveWorkspace(string $memberId): ?Workspace
    {
        $subscription = Subscription::query()
            ->where('member_id', $memberId)
            ->where('status', SubscriptionStatus::Active->value)
            ->latest()
            ->with('workspace')
            ->first();

        return $subscription?->workspace;
    }
}
