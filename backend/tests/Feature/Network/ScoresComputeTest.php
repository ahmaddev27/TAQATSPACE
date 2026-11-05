<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Enums\SubscriberSegment;
use App\Enums\WorkspaceFeature;
use App\Models\SubscriberScore;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FeatureFlagService;
use App\Services\Network\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Task 3.7: loyalty and strength scores, segments and the scores:compute command. */
class ScoresComputeTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create(['timezone' => 'UTC']);
        app(FeatureFlagService::class)->update($this->workspace, [WorkspaceFeature::Network->value => true]);
    }

    /** One stored month, `$monthsAgo` months before the current one; activity falls inside the month's days. */
    private function month(string $subscriberId, int $monthsAgo, array $overrides = [], ?Workspace $workspace = null): array
    {
        $first = now()->startOfMonth()->subMonths($monthsAgo);

        return array_merge([
            'workspace_id' => ($workspace ?? $this->workspace)->id,
            'subscriber_id' => $subscriberId,
            'month' => $first->toDateString(),
            'attendance_days' => 18,
            'attendance_rate' => 90,
            'active_actual_minutes' => 18 * 300,
            'day_span_minutes' => 18 * 400,
            'hour_bucket_hours' => 60,
            'half_hour_slots' => 100,
            'bytes_in' => 0,
            'bytes_out' => 0,
            'first_activity_at' => $first->copy()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
            'last_activity_at' => $first->copy()->addDays(20)->setTime(9, 0)->format('Y-m-d H:i:s'),
        ], $overrides);
    }

    private function regularRows(string $subscriberId, int $lastActivityDaysAgo = 1): array
    {
        $rows = [];

        foreach ([0, 1, 2] as $ago) {
            $rows[] = $this->month($subscriberId, $ago);
        }
        // A year of history makes the tenure long; the newest month ends at the requested last activity.
        $rows[] = $this->month($subscriberId, 8, ['first_activity_at' => now()->subDays(240)->format('Y-m-d H:i:s')]);
        $last = now()->subDays($lastActivityDaysAgo)->format('Y-m-d H:i:s');

        // No month may end after the requested last activity, or the lifetime maximum would hide it.
        return array_map(fn (array $row): array => array_merge($row, ['last_activity_at' => min($row['last_activity_at'], $last)]), $rows);
    }

    private function subscribe(User $user): void
    {
        Subscription::factory()->create([
            'member_id' => $user->id,
            'workspace_id' => $this->workspace->id,
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => null,
        ]);
    }

    private function score(User $user): SubscriberScore
    {
        return SubscriberScore::query()->withoutWorkspaceScope()->where('subscriber_id', $user->id)->firstOrFail();
    }

    public function test_a_regular_attender_with_a_contract_is_a_champion(): void
    {
        $user = User::factory()->create();
        $this->subscribe($user);
        DB::table('stats_monthly_subscriber')->insert($this->regularRows($user->id));

        $this->artisan('scores:compute')->assertSuccessful();

        $score = $this->score($user);
        $this->assertSame(SubscriberSegment::Champion, $score->segment);
        $this->assertGreaterThanOrEqual(80.0, (float) $score->loyalty_score);
        $this->assertGreaterThan(80.0, (float) $score->strength_score);
        $this->assertSame(1, $score->components['metrics']['days_since_last_activity']);
    }

    public function test_no_activity_for_14_days_is_at_risk_and_60_is_dormant(): void
    {
        [$risky, $gone] = User::factory()->count(2)->create();
        DB::table('stats_monthly_subscriber')->insert([...$this->regularRows($risky->id, 14), ...$this->regularRows($gone->id, 60)]);

        $this->artisan('scores:compute');

        $this->assertSame(SubscriberSegment::AtRisk, $this->score($risky)->segment);
        $this->assertSame(SubscriberSegment::Dormant, $this->score($gone)->segment);
    }

    public function test_a_subscriber_who_just_started_is_new(): void
    {
        $user = User::factory()->create();
        DB::table('stats_monthly_subscriber')->insert($this->month($user->id, 0, ['first_activity_at' => now()->subDays(5)->format('Y-m-d H:i:s'), 'last_activity_at' => now()->format('Y-m-d H:i:s')]));

        $this->artisan('scores:compute');

        $this->assertSame(SubscriberSegment::New, $this->score($user)->segment);
    }

    public function test_the_attendance_rate_is_read_from_the_stored_statistics(): void
    {
        $user = User::factory()->create();
        DB::table('stats_monthly_subscriber')->insert(array_map(
            fn (int $ago): array => $this->month($user->id, $ago, ['attendance_rate' => 40]),
            [0, 1, 2],
        ));

        $this->artisan('scores:compute');

        $this->assertSame(40.0, (float) $this->score($user)->components['loyalty']['regularity']);
    }

    public function test_a_changed_weight_changes_the_score_only_after_a_recalculation(): void
    {
        $user = User::factory()->create();
        DB::table('stats_monthly_subscriber')->insert($this->regularRows($user->id, 10));
        $this->artisan('scores:compute');
        $before = (float) $this->score($user)->loyalty_score;

        $this->workspace->update(['scoring' => ['weights' => ['loyalty' => ['regularity' => 0, 'recency' => 1, 'tenure' => 0, 'engagement' => 0]]]]);
        $this->assertSame($before, (float) $this->score($user)->loyalty_score);

        $this->artisan('scores:compute');
        $this->assertNotSame($before, (float) $this->score($user)->loyalty_score);
    }

    public function test_the_same_inputs_give_the_same_scores(): void
    {
        $user = User::factory()->create();
        DB::table('stats_monthly_subscriber')->insert($this->regularRows($user->id, 3));
        $now = now()->toImmutable();

        app(ScoringService::class)->compute($this->workspace, $now);
        $first = $this->score($user)->only(['loyalty_score', 'strength_score', 'segment', 'components']);
        app(ScoringService::class)->compute($this->workspace, $now);

        $this->assertEquals($first, $this->score($user)->only(['loyalty_score', 'strength_score', 'segment', 'components']));
        $this->assertSame(1, SubscriberScore::query()->withoutWorkspaceScope()->count());
    }

    public function test_a_subscriber_without_a_contract_is_weaker(): void
    {
        [$contracted, $loose] = User::factory()->count(2)->create();
        $this->subscribe($contracted);
        DB::table('stats_monthly_subscriber')->insert([...$this->regularRows($contracted->id), ...$this->regularRows($loose->id)]);

        $this->artisan('scores:compute');

        $this->assertSame(0.0, (float) $this->score($loose)->components['strength']['contract']);
        $this->assertSame(100.0, (float) $this->score($contracted)->components['strength']['contract']);
    }

    public function test_scores_of_subscribers_without_statistics_are_dropped(): void
    {
        $user = User::factory()->create();
        DB::table('stats_monthly_subscriber')->insert($this->regularRows($user->id));
        $this->artisan('scores:compute');

        DB::table('stats_monthly_subscriber')->delete();
        $this->artisan('scores:compute');

        $this->assertSame(0, SubscriberScore::query()->withoutWorkspaceScope()->count());
    }

    public function test_workspaces_without_the_network_module_are_left_alone_and_a_named_one_can_be_computed(): void
    {
        $other = Workspace::factory()->create(['timezone' => 'UTC']);
        $user = User::factory()->create();
        DB::table('stats_monthly_subscriber')->insert($this->month($user->id, 0, [], $other));

        $this->artisan('scores:compute')->assertSuccessful();
        $this->assertSame(0, SubscriberScore::query()->withoutWorkspaceScope()->count());

        $this->artisan('scores:compute', ['workspace' => $other->id])->assertSuccessful();
        $this->assertSame(1, SubscriberScore::query()->withoutWorkspaceScope()->count());
    }

    public function test_an_unknown_workspace_fails(): void
    {
        $this->artisan('scores:compute', ['workspace' => (string) Str::uuid()])->assertFailed();
    }

    public function test_one_thousand_subscribers_cost_the_same_number_of_queries_as_one(): void
    {
        $users = User::factory()->count(1000)->create();
        DB::table('stats_monthly_subscriber')->insert($this->month($users[0]->id, 0));
        $small = $this->queriesFor(fn () => app(ScoringService::class)->compute($this->workspace));

        $rest = $users->skip(1)->map(fn (User $user): array => $this->month($user->id, 0))->values();
        foreach ($rest->chunk(200) as $chunk) {
            DB::table('stats_monthly_subscriber')->insert($chunk->values()->all());
        }
        $large = $this->queriesFor(fn () => app(ScoringService::class)->compute($this->workspace));

        $this->assertSame(1000, SubscriberScore::query()->withoutWorkspaceScope()->count());
        // The upsert is chunked by 500 rows, so 1000 subscribers add exactly one extra write.
        $this->assertLessThanOrEqual($small + 1, $large);
    }

    private function queriesFor(callable $work): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $work();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
}
