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
        private readonly SeatTypePricingService $seatTypePricing,
        private readonly SeatService $seats,
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
            throw new RuntimeException(__('messages.workspace_already_registered'));
        }

        /** @var array<int, array<string, mixed>> $seatTypes */
        $seatTypes = $data['seat_types'] ?? [];
        unset($data['seat_types']);

        $data['owner_id'] = $ownerId;
        $data['status'] = WorkspaceStatus::Pending->value;

        return DB::transaction(function () use ($data, $seatTypes): Workspace {
            $workspace = $this->workspaces->create($data);

            if ($seatTypes !== []) {
                $this->seatTypePricing->sync($workspace, $seatTypes);
                $this->syncDerivedPricing($workspace);
                $this->seats->syncSeatsToCapacity($workspace);
            }

            return $workspace;
        });
    }

    /**
     * Update mutable profile fields. status/owner_id are never accepted here.
     * total_seats/price_per_month are derived from seat types — never client-set.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSettings(Workspace $workspace, array $data): Workspace
    {
        unset($data['status'], $data['owner_id'], $data['total_seats'], $data['price_per_month']);

        return $this->workspaces->update($workspace, $data);
    }

    /**
     * Upsert the owner's per-seat-type pricing, then recompute the derived
     * legacy columns so public discovery (price filter/sort) stays accurate.
     * Seat types are the single source of truth for pricing & capacity.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function updateSeatTypes(Workspace $workspace, array $rows): Workspace
    {
        return DB::transaction(function () use ($workspace, $rows): Workspace {
            $this->seatTypePricing->sync($workspace, $rows);
            $updated = $this->syncDerivedPricing($workspace);
            $this->seats->syncSeatsToCapacity($workspace);

            return $updated;
        });
    }

    /**
     * Recompute the legacy discovery columns from the enabled seat types — the
     * single source of truth. Kept (not dropped) so public price filter/sort and
     * the seat-capacity ceiling keep working without a schema change:
     *   - total_seats     = sum of capacity over enabled types.
     *   - price_per_month = the lowest monthly price among enabled types
     *                       (the public "starting from" figure). When no enabled
     *                       type has a monthly price, fall back to the lowest
     *                       daily price; if neither exists, keep the prior value.
     */
    public function syncDerivedPricing(Workspace $workspace): Workspace
    {
        $enabled = $workspace->seatTypes()
            ->where('enabled', true)
            ->get();

        $totalSeats = (int) $enabled->sum('capacity');

        $monthly = $enabled
            ->pluck('price_monthly')
            ->filter(static fn ($price): bool => $price !== null)
            ->map(static fn ($price): float => (float) $price);

        $startingPrice = $monthly->isNotEmpty()
            ? (float) $monthly->min()
            : $this->fallbackPrice($enabled, $workspace);

        return $this->workspaces->update($workspace, [
            'total_seats' => max(1, $totalSeats),
            'price_per_month' => $startingPrice,
        ]);
    }

    /**
     * Fallback "starting from" price when no enabled type has a monthly price:
     * the lowest daily price among enabled types, else the workspace's current
     * value (so we never overwrite a meaningful price with zero).
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\SeatTypePrice>  $enabled
     */
    private function fallbackPrice($enabled, Workspace $workspace): float
    {
        $daily = $enabled
            ->pluck('price_daily')
            ->filter(static fn ($price): bool => $price !== null)
            ->map(static fn ($price): float => (float) $price);

        if ($daily->isNotEmpty()) {
            return (float) $daily->min();
        }

        return (float) ($workspace->price_per_month ?? 0);
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
     * Admin publish gate. Marks the workspace as publicly published (sets
     * `published_at`) so it appears on the public landing/discovery. Separate
     * from the account `status`; idempotent (a stamp is set only when missing).
     */
    public function publish(Workspace $workspace): Workspace
    {
        if ($workspace->isPublished()) {
            return $workspace;
        }

        return $this->workspaces->update($workspace, ['published_at' => now()]);
    }

    /**
     * Admin unpublish. Clears `published_at`, hiding the workspace from public
     * discovery again without touching its account `status`.
     */
    public function unpublish(Workspace $workspace): Workspace
    {
        if (! $workspace->isPublished()) {
            return $workspace;
        }

        return $this->workspaces->update($workspace, ['published_at' => null]);
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
            throw new RuntimeException(__('messages.photos_max_reached', ['max' => self::MAX_PHOTOS]));
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
            throw new RuntimeException(__('messages.photo_not_found'));
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
