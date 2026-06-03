<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InternetPackage;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class PackageService
{
    /**
     * List a workspace's packages with assigned-member counts.
     *
     * @return Collection<int, InternetPackage>
     */
    public function listForWorkspace(Workspace $workspace): Collection
    {
        return $workspace->internetPackages()
            ->withCount('members')
            ->latest()
            ->get();
    }

    /**
     * Create a package owned by the given workspace.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Workspace $workspace, array $data): InternetPackage
    {
        return $workspace->internetPackages()->create($this->normalize($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(InternetPackage $package, array $data): InternetPackage
    {
        $package->update($this->normalize($data));

        return $package->refresh();
    }

    public function delete(InternetPackage $package): void
    {
        $package->delete();
    }

    /**
     * Assign a member to a package (idempotent — keeps a single pivot row and
     * refreshes its assignment timestamp).
     */
    public function assignMember(InternetPackage $package, string $memberId): InternetPackage
    {
        $package->members()->syncWithoutDetaching([
            $memberId => ['assigned_at' => Carbon::now()],
        ]);

        return $package->load('members');
    }

    public function unassignMember(InternetPackage $package, string $memberId): InternetPackage
    {
        $package->members()->detach($memberId);

        return $package->load('members');
    }

    /**
     * Unlimited packages never carry a numeric data limit.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        if (array_key_exists('is_unlimited', $data) && (bool) $data['is_unlimited'] === true) {
            $data['data_limit_gb'] = null;
        }

        return $data;
    }
}
