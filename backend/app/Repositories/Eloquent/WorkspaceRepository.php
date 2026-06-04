<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Data access for workspaces. Builds discovery/admin queries; all business
 * decisions (which filters to apply) are made by the service layer.
 */
class WorkspaceRepository
{
    /**
     * Public discovery listing — only active workspaces, conditionally filtered.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginatePublic(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Workspace::query()->active()->with('seatTypes');

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort'] ?? null);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Admin listing — every status, optionally filtered, with owner eager-loaded.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Workspace::query()->with(['owner', 'seatTypes']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['city'])) {
            $query->inCity((string) $filters['city']);
        }

        if (! empty($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    /**
     * Distinct city names across active workspaces, alphabetically sorted.
     *
     * @return array<int, string>
     */
    public function activeCities(): array
    {
        return Workspace::query()
            ->active()
            ->whereNotNull('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->all();
    }

    public function findActiveById(string $id): ?Workspace
    {
        return Workspace::query()->active()->with('seatTypes')->find($id);
    }

    public function findByOwnerId(string $ownerId): ?Workspace
    {
        return Workspace::query()->where('owner_id', $ownerId)->first();
    }

    public function ownerHasWorkspace(string $ownerId): bool
    {
        return Workspace::query()->where('owner_id', $ownerId)->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Workspace
    {
        return Workspace::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Workspace $workspace, array $attributes): Workspace
    {
        $workspace->fill($attributes)->save();

        return $workspace->refresh();
    }

    /**
     * @param  Builder<Workspace>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['city'])) {
            $query->inCity((string) $filters['city']);
        }

        $min = $filters['min_price'] ?? null;
        $max = $filters['max_price'] ?? null;

        if ($min !== null || $max !== null) {
            $query->priceRange(
                (float) ($min ?? 0),
                (float) ($max ?? PHP_INT_MAX),
            );
        }

        if (! empty($filters['amenities']) && is_array($filters['amenities'])) {
            $query->withAmenities(array_values($filters['amenities']));
        }

        if (isset($filters['min_rating']) && $filters['min_rating'] !== null) {
            $query->minRating((float) $filters['min_rating']);
        }

        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }
    }

    /**
     * @param  Builder<Workspace>  $query
     */
    private function applySort(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price_per_month'),
            'price_desc' => $query->orderByDesc('price_per_month'),
            'rating_desc' => $query->orderByDesc('avg_rating'),
            default => $query->latest(),
        };
    }
}
