<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SeatStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WorkspaceStatus;
use App\Models\Review;
use App\Models\Seat;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Repositories\Eloquent\WorkspaceRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class WorkspaceService
{
    private const MAX_PHOTOS = 10;

    /** Disk used for workspace photos (`public` in dev, `s3` on servers). */
    private function photoDisk(): string
    {
        return (string) config('filesystems.media', 'public');
    }

    public function __construct(
        private readonly WorkspaceRepository $workspaces,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function discover(array $filters): LengthAwarePaginator
    {
        return $this->workspaces->paginatePublic($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listForAdmin(array $filters): LengthAwarePaginator
    {
        return $this->workspaces->paginateForAdmin($filters);
    }

    /**
     * @return array<int, string>
     */
    public function activeCities(): array
    {
        return $this->workspaces->activeCities();
    }

    public function findActivePublic(string $id): ?Workspace
    {
        return $this->workspaces->findActiveById($id);
    }

    /**
     * Create the owner's single workspace. Status starts as pending.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws RuntimeException when the owner already has a workspace
     */
    public function createForOwner(string $ownerId, array $data): Workspace
    {
        if ($this->workspaces->ownerHasWorkspace($ownerId)) {
            throw new RuntimeException('You already have a registered workspace.');
        }

        $data['owner_id'] = $ownerId;
        $data['status'] = WorkspaceStatus::Pending->value;

        return $this->workspaces->create($data);
    }

    /**
     * Update mutable profile fields. status/owner_id are never accepted here.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSettings(Workspace $workspace, array $data): Workspace
    {
        unset($data['status'], $data['owner_id']);

        return $this->workspaces->update($workspace, $data);
    }

    /**
     * Admin status transition. Suspending also pauses active subscriptions.
     */
    public function changeStatus(Workspace $workspace, WorkspaceStatus $status): Workspace
    {
        return DB::transaction(function () use ($workspace, $status): Workspace {
            $workspace = $this->workspaces->update($workspace, ['status' => $status->value]);

            if ($status === WorkspaceStatus::Suspended) {
                Subscription::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('status', SubscriptionStatus::Active->value)
                    ->update(['status' => SubscriptionStatus::Suspended->value]);
            }

            return $workspace;
        });
    }

    /**
     * Seat occupancy snapshot computed inline (seats owned by another module).
     *
     * @return array{total: int, available: int, occupied: int, maintenance: int}
     */
    public function seatsSummary(string $workspaceId): array
    {
        $counts = Seat::query()
            ->where('workspace_id', $workspaceId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => (int) $counts->sum(),
            'available' => (int) $counts->get(SeatStatus::Available->value, 0),
            'occupied' => (int) $counts->get(SeatStatus::Occupied->value, 0),
            'maintenance' => (int) $counts->get(SeatStatus::Maintenance->value, 0),
        ];
    }

    /**
     * Up to five most recent reviews (reviewer first name only).
     *
     * @return array<int, array{rating: int, comment: ?string, reviewer: string, created_at: ?string}>
     */
    public function recentReviews(string $workspaceId, int $limit = 5): array
    {
        return Review::query()
            ->where('workspace_id', $workspaceId)
            ->with('member:id,name')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(static fn (Review $review): array => [
                'rating' => $review->rating,
                'comment' => $review->comment,
                'reviewer' => self::firstName($review->member?->name),
                'created_at' => $review->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Store uploaded photos on the public disk and append their paths to the
     * workspace's photos JSON. Enforces the 10-photo cap across existing + new.
     *
     * @param  array<int, UploadedFile>  $files
     *
     * @throws RuntimeException when the total would exceed the photo cap
     */
    public function addPhotos(Workspace $workspace, array $files): Workspace
    {
        $existing = $workspace->photos ?? [];

        if (count($existing) + count($files) > self::MAX_PHOTOS) {
            throw new RuntimeException('A workspace may have at most '.self::MAX_PHOTOS.' photos.');
        }

        $directory = 'workspaces/'.$workspace->id;
        $paths = [];

        foreach ($files as $file) {
            $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
            $paths[] = $file->storeAs($directory, $filename, ['disk' => $this->photoDisk()]);
        }

        return $this->workspaces->update($workspace, [
            'photos' => array_values([...$existing, ...$paths]),
        ]);
    }

    /**
     * Remove a photo (by stored path) from disk and from the photos JSON.
     *
     * @throws RuntimeException when the path is not part of this workspace
     */
    public function removePhoto(Workspace $workspace, string $path): Workspace
    {
        $existing = $workspace->photos ?? [];

        if (! in_array($path, $existing, true)) {
            throw new RuntimeException('Photo not found on this workspace.');
        }

        $disk = Storage::disk($this->photoDisk());

        if ($disk->exists($path)) {
            $disk->delete($path);
        }

        return $this->workspaces->update($workspace, [
            'photos' => array_values(array_filter(
                $existing,
                static fn (string $stored): bool => $stored !== $path,
            )),
        ]);
    }

    private static function firstName(?string $name): string
    {
        if ($name === null || $name === '') {
            return 'Anonymous';
        }

        return explode(' ', trim($name))[0];
    }
}
