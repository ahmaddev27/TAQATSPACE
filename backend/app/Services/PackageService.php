<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InternetPackage;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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
            // Eager-load the assigned members (and their seat in THIS workspace)
            // so the "assigned members" list renders names, avatars and seats —
            // not just the count.
            ->with(['members' => function ($query) use ($workspace): void {
                $query->select('users.id', 'users.name', 'users.avatar')
                    ->with(['subscriptions' => function ($sub) use ($workspace): void {
                        $sub->where('workspace_id', $workspace->id)
                            ->with('seat:id,seat_number');
                    }]);
            }])
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
        $alreadyAssigned = $package->members()
            ->where('member_id', $memberId)
            ->exists();

        if ($alreadyAssigned) {
            $package->members()->updateExistingPivot($memberId, [
                'assigned_at' => Carbon::now(),
            ]);
        } else {
            // The member_package pivot has a UUID primary key with no DB default,
            // and attach() inserts via the query builder (no model events), so the
            // id must be supplied explicitly or MySQL rejects the row.
            $package->members()->attach($memberId, [
                'id' => (string) Str::uuid(),
                'assigned_at' => Carbon::now(),
            ]);
        }

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
