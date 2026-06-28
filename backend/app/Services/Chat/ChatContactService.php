<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WorkspaceStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Support\MediaUrl;

/**
 * Derives the realtime-chat contact list for a user, scoped to their role.
 *
 *  - A workspace **owner** chats with their members (freelancers subscribed to
 *    their workspace).
 *  - A **freelancer** chats with workspaces — shown by WORKSPACE name, not the
 *    owner's personal name — covering every active workspace plus any they
 *    subscribe(d) to (flagged `subscribed`). Freelancers do NOT chat with admins.
 *  - An **admin** chats with any active owner or freelancer; owners are labelled
 *    "owner name - workspace name" so the space is clear.
 *
 * Each contact carries `{ id, name, workspace_id, role, subscribed }`; `role`
 * lets the SPA filter by type. Contacts are de-duplicated by user id.
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
     * can group/filter the list by user type. `name` may be overridden with a
     * role-appropriate display label (workspace name, or "owner - workspace").
     * `subscribed` flags a workspace the viewer is a member of (for a badge).
     *
     * @return array{id: string, name: string, avatar: ?string, workspace_id: string, role: string, subscribed: bool}
     */
    private function contact(
        User $user,
        string $workspaceId,
        ?string $displayName = null,
        bool $subscribed = false,
        ?string $avatar = null,
    ): array {
        return [
            'id' => (string) $user->id,
            'name' => $displayName ?? (string) $user->name,
            // When a contact stands in for a workspace, the caller passes the
            // workspace logo; otherwise we show the user's own avatar.
            'avatar' => $avatar ?? MediaUrl::resolve($user->avatar),
            'workspace_id' => $workspaceId,
            'role' => $user->role->value,
            'subscribed' => $subscribed,
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
            ->with('member:id,name,role,avatar')
            ->where('workspace_id', $workspace->id)
            ->get(['id', 'member_id', 'workspace_id'])
            ->filter(static fn (Subscription $s): bool => $s->member !== null)
            ->unique('member_id')
            ->map(fn (Subscription $s): array => $this->contact($s->member, (string) $s->workspace_id))
            ->values()
            ->all();
    }

    /**
     * A freelancer chats with workspaces — every active workspace plus any they
     * subscribe(d) to (kept reachable even when inactive). Each is shown by the
     * WORKSPACE name (not the owner's personal name), flagged `subscribed` when
     * the freelancer is a member. Freelancers do NOT chat with admins.
     *
     * @return list<array{id: string, name: string, workspace_id: string, role: string, subscribed: bool}>
     */
    private function freelancerContacts(User $freelancer): array
    {
        // Workspaces this freelancer is a member of — drives the "subscribed"
        // badge and keeps their owners reachable even if a space goes inactive.
        $subscribedIds = Subscription::query()
            ->where('member_id', $freelancer->id)
            ->pluck('workspace_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
        $subscribedSet = array_flip($subscribedIds);

        $contacts = [];

        Workspace::query()
            ->where(function ($query) use ($subscribedIds): void {
                $query->where('status', WorkspaceStatus::Active->value);
                if ($subscribedIds !== []) {
                    $query->orWhereIn('id', $subscribedIds);
                }
            })
            ->with('owner:id,name,role,avatar')
            ->get(['id', 'name', 'owner_id', 'logo_path'])
            ->each(function (Workspace $workspace) use (&$contacts, $subscribedSet): void {
                if ($workspace->owner === null) {
                    return;
                }

                // Keyed by owner id (the chat counterpart), labelled and pictured
                // by the workspace (its logo, falling back to the owner avatar).
                $contacts[(string) $workspace->owner->id] = $this->contact(
                    $workspace->owner,
                    (string) $workspace->id,
                    (string) $workspace->name,
                    isset($subscribedSet[(string) $workspace->id]),
                    $workspace->logoUrl(),
                );
            });

        // Never list yourself.
        unset($contacts[(string) $freelancer->id]);

        return array_values($contacts);
    }

    /**
     * Platform-wide contacts for an admin: every active owner and freelancer
     * (excluding the admin themselves), ordered by name. Owners are labelled
     * "owner name - workspace name" so the admin sees which space they manage.
     * The SPA searches/filters this list client-side.
     *
     * @return list<array{id: string, name: string, workspace_id: string, role: string, subscribed: bool}>
     */
    private function adminContacts(User $admin): array
    {
        return User::query()
            ->whereIn('role', [UserRole::WorkspaceOwner->value, UserRole::Freelancer->value])
            ->where('status', UserStatus::Active->value)
            ->whereKeyNot($admin->id)
            ->with('workspace:id,name,owner_id,logo_path')
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'avatar'])
            ->map(function (User $user): array {
                // Owners → "name - workspace" + workspace logo (fall back to the
                // owner avatar); freelancers keep their own name and avatar.
                if ($user->isOwner() && $user->workspace !== null) {
                    return $this->contact(
                        $user,
                        (string) $user->workspace->id,
                        $user->name.' - '.$user->workspace->name,
                        false,
                        MediaUrl::resolve($user->workspace->logo_path) ?? MediaUrl::resolve($user->avatar),
                    );
                }

                return $this->contact($user, '');
            })
            ->all();
    }
}
