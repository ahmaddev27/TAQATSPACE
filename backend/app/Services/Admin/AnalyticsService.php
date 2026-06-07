<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\Gender;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;

/**
 * Demographic + geographic analytics for the super-admin dashboard.
 *
 * Two breakdowns, each computed with a single grouped aggregate query (no
 * per-row work, no N+1):
 *   - by city: workspaces and active members (subscriptions) per workspace city,
 *     where "city" is the system's location model carried on the workspace.
 *   - by gender: platform users bucketed male / female / unspecified (null).
 *
 * Both return flat `[{ label, value }]` arrays. City labels are the raw stored
 * city values (free-text); the gender bucket keys (`male`/`female`/`unspecified`)
 * are stable tokens the frontend localises.
 */
class AnalyticsService
{
    /** Bucket key for users/members with no recorded gender. */
    private const UNSPECIFIED = 'unspecified';

    /**
     * @return array{
     *     by_city: array<int, array{label: string, workspaces: int, active_members: int}>,
     *     by_gender: array<int, array{label: string, value: int}>
     * }
     */
    public function build(): array
    {
        return [
            'by_city' => $this->byCity(),
            'by_gender' => $this->byGender(),
        ];
    }

    /**
     * Workspaces and active members per workspace city, ordered by workspace
     * count desc. Two grouped queries (workspaces-per-city, active-members-per-
     * city) merged in PHP — no per-city queries.
     *
     * @return array<int, array{label: string, workspaces: int, active_members: int}>
     */
    private function byCity(): array
    {
        $workspaceCounts = Workspace::query()
            ->selectRaw('city as bucket, COUNT(*) as aggregate')
            ->groupBy('city')
            ->pluck('aggregate', 'bucket');

        $memberCounts = Subscription::query()
            ->join('workspaces', 'workspaces.id', '=', 'subscriptions.workspace_id')
            ->where('subscriptions.status', SubscriptionStatus::Active->value)
            ->selectRaw('workspaces.city as bucket, COUNT(*) as aggregate')
            ->groupBy('workspaces.city')
            ->pluck('aggregate', 'bucket');

        return $workspaceCounts
            ->map(fn (int|string $count, int|string $city): array => [
                'label' => (string) $city,
                'workspaces' => (int) $count,
                'active_members' => (int) $memberCounts->get($city, 0),
            ])
            ->sortByDesc('workspaces')
            ->values()
            ->all();
    }

    /**
     * Platform users grouped by gender, with every bucket present and zero-filled
     * (male / female / unspecified). Single grouped query.
     *
     * @return array<int, array{label: string, value: int}>
     */
    private function byGender(): array
    {
        $counts = $this->genderCounts(User::query());

        return $this->genderBuckets($counts);
    }

    /**
     * Single grouped count of users by gender, keyed by the stored value with
     * nulls collapsed under the {@see self::UNSPECIFIED} bucket.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     * @return Collection<string, int>
     */
    private function genderCounts(\Illuminate\Database\Eloquent\Builder $query): Collection
    {
        return $query
            ->selectRaw('gender as bucket, COUNT(*) as aggregate')
            ->groupBy('gender')
            ->pluck('aggregate', 'bucket')
            ->mapWithKeys(fn (int|string $count, int|string|null $gender): array => [
                ($gender === null || $gender === '') ? self::UNSPECIFIED : (string) $gender => (int) $count,
            ]);
    }

    /**
     * Shape gender counts into the contiguous, zero-filled `[{label, value}]`
     * series (male, female, unspecified). Shared so the owner view can reuse it.
     *
     * @param  Collection<string, int>  $counts
     * @return array<int, array{label: string, value: int}>
     */
    public function genderBuckets(Collection $counts): array
    {
        $buckets = [Gender::Male->value, Gender::Female->value, self::UNSPECIFIED];

        return array_map(
            static fn (string $bucket): array => [
                'label' => $bucket,
                'value' => (int) $counts->get($bucket, 0),
            ],
            $buckets,
        );
    }
}
