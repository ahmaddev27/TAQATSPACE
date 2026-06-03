<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Announcement;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\NewAnnouncementNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AnnouncementService
{
    /**
     * Owner-facing list: every announcement of the workspace, including drafts
     * and expired ones, newest first.
     *
     * @return Collection<int, Announcement>
     */
    public function listForOwner(Workspace $workspace): Collection
    {
        return Announcement::query()
            ->forWorkspace($workspace->id)
            ->latest('created_at')
            ->get();
    }

    /**
     * Public list: only currently live (published, non-expired) announcements,
     * newest published first.
     *
     * @return Collection<int, Announcement>
     */
    public function listPublic(Workspace $workspace): Collection
    {
        return Announcement::query()
            ->forWorkspace($workspace->id)
            ->active()
            ->orderByDesc('published_at')
            ->get();
    }

    /**
     * Create an announcement for the owner's workspace. If it is published with
     * a moment that is already in the past, notify all active members.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Workspace $workspace, User $owner, array $data): Announcement
    {
        $announcement = DB::transaction(static function () use ($workspace, $owner, $data): Announcement {
            return Announcement::query()->create([
                'workspace_id' => $workspace->id,
                'created_by' => $owner->id,
                'type' => $data['type'],
                'title' => $data['title'],
                'body' => $data['body'],
                'published_at' => $data['published_at'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
            ]);
        });

        if ($this->isLive($announcement)) {
            $this->notifyActiveMembers($workspace, $announcement);
        }

        return $announcement;
    }

    /**
     * Update an announcement that belongs to the given workspace. Returns null
     * when the announcement is not part of this workspace. Members are notified
     * when this update transitions the announcement from not-live to live.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Workspace $workspace, Announcement $announcement, array $data): ?Announcement
    {
        if ($announcement->workspace_id !== $workspace->id) {
            return null;
        }

        $wasLive = $this->isLive($announcement);

        $announcement->fill($this->onlyFillable($data));
        $announcement->save();

        $announcement->refresh();

        if (! $wasLive && $this->isLive($announcement)) {
            $this->notifyActiveMembers($workspace, $announcement);
        }

        return $announcement;
    }

    /**
     * Delete an announcement that belongs to the given workspace.
     *
     * @return bool  false when the announcement is not part of this workspace
     */
    public function delete(Workspace $workspace, Announcement $announcement): bool
    {
        if ($announcement->workspace_id !== $workspace->id) {
            return false;
        }

        return (bool) $announcement->delete();
    }

    /**
     * Whether the announcement is published with a past moment and not expired.
     */
    private function isLive(Announcement $announcement): bool
    {
        $now = Carbon::now();

        if ($announcement->published_at === null || $announcement->published_at->isAfter($now)) {
            return false;
        }

        return $announcement->expires_at === null || $announcement->expires_at->isAfter($now);
    }

    /**
     * Notify every active member of the workspace about the announcement.
     * Members are resolved through their active subscriptions (deduplicated).
     */
    private function notifyActiveMembers(Workspace $workspace, Announcement $announcement): void
    {
        $members = User::query()
            ->whereIn('id', function ($query) use ($workspace): void {
                $query->select('member_id')
                    ->from('subscriptions')
                    ->where('workspace_id', $workspace->id)
                    ->where('status', SubscriptionStatus::Active->value);
            })
            ->get();

        if ($members->isEmpty()) {
            return;
        }

        Notification::send(
            $members,
            new NewAnnouncementNotification($announcement, $workspace->name),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function onlyFillable(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'type', 'title', 'body', 'published_at', 'expires_at',
        ]));
    }
}
