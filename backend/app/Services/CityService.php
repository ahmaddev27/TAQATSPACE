<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\City;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CityService
{
    /**
     * List cities ordered by Arabic name. When $activeOnly, only active cities.
     *
     * @return Collection<int, City>
     */
    public function list(bool $activeOnly = false): Collection
    {
        return City::query()
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('name_ar')
            ->get();
    }

    /**
     * List cities with their workspace counts (admin view).
     *
     * @return Collection<int, City>
     */
    public function listWithCounts(): Collection
    {
        return City::query()
            ->withCount('workspaces')
            ->orderBy('name_ar')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): City
    {
        return City::create([
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update a city. When the Arabic name changes, propagate it to the
     * denormalized `workspaces.city` display column so existing readers stay
     * consistent with the renamed city.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(City $city, array $data): City
    {
        $renamed = array_key_exists('name_ar', $data)
            && $data['name_ar'] !== $city->name_ar;

        return DB::transaction(function () use ($city, $data, $renamed): City {
            $city->fill([
                'name_ar' => $data['name_ar'] ?? $city->name_ar,
                'name_en' => $data['name_en'] ?? $city->name_en,
                'is_active' => $data['is_active'] ?? $city->is_active,
            ])->save();

            if ($renamed) {
                Workspace::query()
                    ->where('city_id', $city->id)
                    ->update(['city' => $city->name_ar]);
            }

            return $city;
        });
    }

    /**
     * Delete a city. When workspaces still reference it, do NOT hard-delete
     * (that would orphan their display string and break the FK contract) —
     * soft-disable it instead. Only an unused city is actually removed.
     *
     * @return array{deleted: bool}  deleted=false means it was deactivated instead
     */
    public function delete(City $city): array
    {
        if ($city->workspaces()->exists()) {
            $city->update(['is_active' => false]);

            return ['deleted' => false];
        }

        $city->delete();

        return ['deleted' => true];
    }
}
