<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WorkspaceStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;

/**
 * Derives the realtime-chat contact list for a user, scoped to their role.
 *
 *  - A workspace **owner** chats with their members (freelancers subscribed to
 *    their workspace).
 *  - A **freelancer** chats with the owner of every active workspace, the
 *    owner(s) of workspaces they subscribe(d) to (kept reachable even when the
 *    workspace is no longer active), and the platform admin(s).
 *  - An **admin** chats with any active owner or freelancer (platform support).
 *
 * Each contact carries `{ id, name, workspace_id, role }`; `role` lets the SPA
 * offer a by-type filter. Contacts are de-duplicated by user id.
 */
class ChatContactService
{
    /**
     * @return list<array{id: string, name: string, workspace_id: string, role: string}>
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
     * Shape a single contact row. `role` is the contact's own role, so the SPA
     * can group/filter the list by user type.
     *
     * @return array{id: string, name: string, workspace_id: string, role: string}
     */
    private function contact(User $user, string $workspaceId): array
    {
        return [
            'id' => (string) $user->id,
            'name' => (string) $user->name,
            'workspace_id' => $workspaceId,
            'role' => $user->role->value,
        ];
    }

    /**
     * The owner's members: distinct freelancers subscribed to their workspace.
     *
     * @return list<array{id: string, name: string, workspace_id: string, role: string}>
     */
    private function ownerContacts(User $owner): array
    {
        $workspace = $owner->workspace;

        if ($workspace === null) {
            return [];
        }

        return Subscription::query()
            ->with('member:id,name,role')
            ->where('workspace_id', $workspace->id)
            ->get(['id', 'member_id', 'workspace_id'])
            ->filter(static fn (Subscription $s): bool => $s->member !== null)
            ->unique('member_id')
            ->map(fn (Subscription $s): array => $this->contact($s->member, (string) $s->workspace_id))
            ->values()
            ->all();
    }

    /**
     * A freelancer chats with: the owner of every active workspace, the owner(s)
     * of workspaces they subscribe(d) to, and the platform admin(s).
     *
     * @return list<array{id: string, name: string, workspace_id: string, role: string}>
     */
    private function freelancerContacts(User $freelancer): array
    {
        $contacts = [];

        // 1. Owners of every active workspace — reach out to any open space.
        Workspace::query()
            ->where('status', WorkspaceStatus::Active->value)
            ->with('owner:id,name,role')
            ->get(['id', 'owner_id'])
            ->each(function (Workspace $workspace) use (&$contacts): void {
                if ($workspace->owner !== null) {
                    $contacts[(string) $workspace->owner->id] =
                        $this->contact($workspace->owner, (string) $workspace->id);
                }
            });

        // 2. Owners of subscribed workspaces (even if not active), so an existing
        //    conversation stays reachable after a workspace is suspended/closed.
        Subscription::query()
            ->with('workspace.owner:id,name,role')
            ->where('member_id', $freelancer->id)
            ->get(['id', 'workspace_id'])
            ->each(function (Subscription $subscription) use (&$contacts): void {
                $owner = $subscription->workspace?->owner;

                if ($owner !== null && ! isset($contacts[(string) $owner->id])) {
                    $contacts[(string) $owner->id] =
                        $this->contact($owner, (string) $subscription->workspace_id);
                }
            });

        // 3. Platform admins (support). `+` keeps any already-collected entry.
        $contacts += $this->adminsKeyedById();

        // Never list yourself.
        unset($contacts[(string) $freelancer->id]);

        return array_values($contacts);
    }

    /**
     * Platform-wide contacts for an admin: every active owner and freelancer
     * (excluding the admin themselves), ordered by name. The SPA searches/filters
     * this list client-side.
     *
     * @return list<array{id: string, name: string, workspace_id: string, role: string}>
     */
    private function adminContacts(User $admin): array
    {
        return User::query()
            ->whereIn('role', [UserRole::WorkspaceOwner->value, UserRole::Freelancer->value])
            ->where('status', UserStatus::Active->value)
            ->whereKeyNot($admin->id)
            ->orderBy('name')
            ->get(['id', 'name', 'role'])
            ->map(fn (User $user): array => $this->contact($user, ''))
            ->all();
    }

    /**
     * Active platform admins keyed by id — a support contact for every member.
     *
     * @return array<string, array{id: string, name: string, workspace_id: string, role: string}>
     */
    private function adminsKeyedById(): array
    {
        return User::query()
            ->where('role', UserRole::Admin->value)
            ->where('status', UserStatus::Active->value)
            ->get(['id', 'name', 'role'])
            ->mapWithKeys(fn (User $admin): array => [
                (string) $admin->id => $this->contact($admin, ''),
            ])
            ->all();
    }
}
