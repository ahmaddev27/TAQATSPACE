<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Subscription;
use App\Models\User;

/**
 * Derives the realtime-chat contact list for a user, scoped to their role.
 *
 *  - A workspace **owner** may chat with every freelancer who holds (or held) a
 *    subscription to their workspace — i.e. their members.
 *  - A **freelancer** may chat with the owner(s) of the workspace(s) they are
 *    subscribed to.
 *  - An **admin** may start a conversation with any active owner or freelancer
 *    (platform-wide support); the SPA provides client-side search over the list.
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

        if ($user->isAdmin()) {
            return $this->adminContacts($user);
        }

        return [];
    }

    /**
     * Platform-wide contacts for an admin: every active owner and freelancer
     * (excluding the admin themselves), ordered by name. `workspace_id` is not
     * meaningful here — the conversation id is derived from the participant ids
     * alone — so it is returned empty. The SPA filters/searches this list
     * client-side.
     *
     * @return list<array{id: string, name: string, workspace_id: string}>
     */
    private function adminContacts(User $admin): array
    {
        return User::query()
            ->whereIn('role', [UserRole::WorkspaceOwner->value, UserRole::Freelancer->value])
            ->where('status', UserStatus::Active->value)
            ->whereKeyNot($admin->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (User $user): array => [
                'id' => (string) $user->id,
                'name' => (string) $user->name,
                'workspace_id' => '',
            ])
            ->all();
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
