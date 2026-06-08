<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\Subscription;
use App\Models\User;

/**
 * Derives the realtime-chat contact list for a user, scoped to their role.
 *
 *  - A workspace **owner** may chat with every freelancer who holds (or held) a
 *    subscription to their workspace — i.e. their members.
 *  - A **freelancer** may chat with the owner(s) of the workspace(s) they are
 *    subscribed to.
 *
 * The result is the minimal shape the SPA needs to open a conversation:
 * `[{ id, name, workspace_id }]`. Contacts are de-duplicated by user id.
 */
class ChatContactService
{
    /**
     * Resolve the chat-able contacts for the given user.
     *
     * @return list<array{id: string, name: string, workspace_id: string}>
     */
    public function contactsFor(User $user): array
    {
        if ($user->isOwner()) {
            return $this->ownerContacts($user);
        }

        if ($user->isFreelancer()) {
            return $this->freelancerContacts($user);
        }

        return [];
    }

    /**
     * The owner's members: distinct freelancers subscribed to their workspace.
     *
     * @return list<array{id: string, name: string, workspace_id: string}>
     */
    private function ownerContacts(User $owner): array
    {
        $workspace = $owner->workspace;

        if ($workspace === null) {
            return [];
        }

        $members = Subscription::query()
            ->with('member:id,name')
            ->where('workspace_id', $workspace->id)
            ->get(['id', 'member_id', 'workspace_id'])
            ->filter(static fn (Subscription $s): bool => $s->member !== null)
            ->unique('member_id');

        return $members
            ->map(static fn (Subscription $s): array => [
                'id' => (string) $s->member->id,
                'name' => (string) $s->member->name,
                'workspace_id' => (string) $s->workspace_id,
            ])
            ->values()
            ->all();
    }

    /**
     * The freelancer's workspace owner(s): one contact per workspace they are
     * (or were) subscribed to. Suspended/cancelled subscriptions still resolve a
     * contact so an existing conversation stays reachable.
     *
     * @return list<array{id: string, name: string, workspace_id: string}>
     */
    private function freelancerContacts(User $freelancer): array
    {
        $subscriptions = Subscription::query()
            ->with('workspace.owner:id,name')
            ->where('member_id', $freelancer->id)
            ->get(['id', 'workspace_id']);

        $contacts = [];

        foreach ($subscriptions as $subscription) {
            $owner = $subscription->workspace?->owner;

            if ($owner === null) {
                continue;
            }

            // De-dup by owner id: a freelancer with several subscriptions to the
            // same workspace still chats with a single owner.
            $contacts[$owner->id] = [
                'id' => (string) $owner->id,
                'name' => (string) $owner->name,
                'workspace_id' => (string) $subscription->workspace_id,
            ];
        }

        return array_values($contacts);
    }
}
