<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Enums\WorkspaceFeature;
use App\Jobs\RecalculateStatsJob;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\HotspotSession;
use App\Models\StatsDailyWorkspace;
use App\Models\StatsMonthlySubscriber;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FeatureFlagService;
use App\Services\Network\StatsAggregator;
use App\Services\Network\StatsRecalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\BuildsSessionFixtures;
use Tests\TestCase;

/** Task 3.5: the owner's "Recalculate" tool and the stats:recalculate command. */
class StatsRecalculationTest extends TestCase
{
    use BuildsSessionFixtures, RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private User $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRouter();
        $this->owner = User::factory()->owner()->create();
        $this->workspace = Workspace::query()->findOrFail($this->router->workspace_id);
        $this->workspace->update(['owner_id' => $this->owner->id]);
        app(FeatureFlagService::class)->update($this->workspace, [WorkspaceFeature::Network->value => true]);
        $this->subscriber = User::factory()->create();
        $this->makeSession($this->workspace, $this->subscriber, '2026-11-05 10:00:00', '2026-11-05 11:00:00', 100);
        Sanctum::actingAs($this->owner);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeSession(Workspace $workspace, User $subscriber, string $start, string $end, int $bytesIn = 0): void
    {
        $device = Device::query()->withoutWorkspaceScope()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'mac' => 'AA:AA:AA:AA:AA:01'],
            ['status' => 'discovered', 'first_seen_at' => now(), 'last_seen_at' => now()],
        );

        HotspotSession::query()->withoutWorkspaceScope()->create([
            'router_id' => $this->router->id,
            'workspace_id' => $workspace->id,
            'device_id' => $device->id,
            'subscriber_id' => $subscriber->id,
            'started_at' => $start,
            'ended_at' => $end,
            'last_seen_at' => $end,
            'missed_snapshots' => 0,
            'bytes_in' => $bytesIn,
            'bytes_out' => 0,
            'source' => 'event',
        ]);
    }

    /** @return array<string, string> */
    private function range(string $from = '2026-11-01', string $to = '2026-11-06'): array
    {
        return ['from' => $from, 'to' => $to];
    }

    /** @return list<array<string, mixed>> */
    private function statRows(): array
    {
        $strip = fn ($row): array => collect($row->getAttributes())->except('id')->all();

        return [
            StatsDailyWorkspace::query()->withoutWorkspaceScope()->orderBy('date')->get()->map($strip)->all(),
            StatsMonthlySubscriber::query()->withoutWorkspaceScope()->orderBy('month')->get()->map($strip)->all(),
        ];
    }

    public function test_the_owner_recalculates_a_range_and_reads_the_finished_status(): void
    {
        $this->postJson('/api/workspace/tools/recalculate', $this->range())
            ->assertStatus(202)
            ->assertJsonPath('data.from', '2026-11-01');

        $this->getJson('/api/workspace/tools/recalculate/status')
            ->assertOk()
            ->assertJsonPath('data.state', 'completed')
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.written.days', 6)
            ->assertJsonPath('data.written.subscriber_months', 1);
        $this->assertSame(6, StatsDailyWorkspace::query()->withoutWorkspaceScope()->count());
        $this->assertSame(100, (int) StatsMonthlySubscriber::query()->withoutWorkspaceScope()->value('bytes_in'));
    }

    public function test_the_status_is_idle_before_any_recalculation(): void
    {
        $this->getJson('/api/workspace/tools/recalculate/status')->assertOk()->assertJsonPath('data.state', 'idle');
    }

    public function test_recalculating_a_month_twice_gives_identical_numbers(): void
    {
        $this->postJson('/api/workspace/tools/recalculate', $this->range())->assertStatus(202);
        $first = $this->statRows();

        $this->postJson('/api/workspace/tools/recalculate', $this->range())->assertStatus(202);

        $this->assertSame($first, $this->statRows());
    }

    public function test_a_recalculation_rebuilds_a_stale_attendance_range_too(): void
    {
        $this->postJson('/api/workspace/tools/recalculate', $this->range())->assertStatus(202);
        $this->makeSession($this->workspace, $this->subscriber, '2026-11-04 10:00:00', '2026-11-04 11:00:00');

        $this->postJson('/api/workspace/tools/recalculate', $this->range())->assertStatus(202);

        $this->assertSame(2, (int) StatsMonthlySubscriber::query()->withoutWorkspaceScope()->value('attendance_days'));
    }

    public function test_the_recalculation_is_audited(): void
    {
        $this->postJson('/api/workspace/tools/recalculate', $this->range())->assertStatus(202);

        $entry = AuditLog::query()->where('action', 'data.recalculated')->firstOrFail();
        $this->assertSame($this->workspace->id, $entry->workspace_id);
        $this->assertSame($this->owner->id, $entry->actor_id);
        $this->assertSame(['from' => '2026-11-01', 'to' => '2026-11-06'], $entry->changes);
    }

    public function test_a_second_request_while_the_first_waits_is_refused(): void
    {
        Queue::fake();

        $this->postJson('/api/workspace/tools/recalculate', $this->range())->assertStatus(202)->assertJsonPath('data.state', 'queued');
        $this->postJson('/api/workspace/tools/recalculate', $this->range())
            ->assertStatus(409)
            ->assertJsonPath('message', __('messages.stats_recalculation_running'));

        Queue::assertPushed(RecalculateStatsJob::class, 1);
        Queue::assertPushedOn('heavy', RecalculateStatsJob::class);
        $this->getJson('/api/workspace/tools/recalculate/status')->assertJsonPath('data.active', true);
    }

    public function test_a_failed_run_releases_the_lock_and_reports_the_failure(): void
    {
        $this->mock(StatsAggregator::class)->shouldReceive('aggregate')->once()->andThrow(new RuntimeException('boom'));

        try {
            app(StatsRecalculationService::class)->request($this->workspace, '2026-11-01', '2026-11-06', $this->owner);
            $this->fail('The failure should surface.');
        } catch (RuntimeException) {
            // Expected: the sync queue rethrows.
        }

        $this->getJson('/api/workspace/tools/recalculate/status')->assertJsonPath('data.state', 'failed');
        $this->assertSame('failed', app(StatsRecalculationService::class)->status($this->workspace)->state);
        Queue::fake();
        $this->postJson('/api/workspace/tools/recalculate', $this->range())->assertStatus(202);
    }

    public function test_an_owner_only_ever_recalculates_their_own_workspace(): void
    {
        $other = User::factory()->owner()->create();
        $otherWorkspace = Workspace::factory()->create(['owner_id' => $other->id]);
        app(FeatureFlagService::class)->update($otherWorkspace, [WorkspaceFeature::Network->value => true]);
        Sanctum::actingAs($other);

        $this->postJson('/api/workspace/tools/recalculate', $this->range())->assertStatus(202);

        $this->assertSame(6, StatsDailyWorkspace::query()->withoutWorkspaceScope()->where('workspace_id', $otherWorkspace->id)->count());
        $this->assertSame(0, StatsDailyWorkspace::query()->withoutWorkspaceScope()->where('workspace_id', $this->workspace->id)->count());
        // The status is private to the workspace too.
        Sanctum::actingAs($this->owner);
        $this->getJson('/api/workspace/tools/recalculate/status')->assertJsonPath('data.state', 'idle');
    }

    public function test_only_an_owner_with_the_network_module_may_use_the_tools(): void
    {
        Sanctum::actingAs(User::factory()->freelancer()->create());
        $this->postJson('/api/workspace/tools/recalculate', $this->range())->assertForbidden();
        $this->getJson('/api/workspace/tools/recalculate/status')->assertForbidden();

        Sanctum::actingAs($this->owner);
        app(FeatureFlagService::class)->update($this->workspace, [WorkspaceFeature::Network->value => false]);
        $this->postJson('/api/workspace/tools/recalculate', $this->range())->assertForbidden();
    }

    public function test_an_unauthenticated_request_is_refused(): void
    {
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/workspace/tools/recalculate', $this->range())->assertUnauthorized();
    }

    #[DataProvider('invalidRanges')]
    public function test_an_invalid_range_is_rejected(array $payload): void
    {
        $this->postJson('/api/workspace/tools/recalculate', $payload)->assertStatus(422);
    }

    /** @return array<string, array{0: array<string, string>}> */
    public static function invalidRanges(): array
    {
        return [
            'missing' => [[]],
            'not a date' => [['from' => 'soon', 'to' => '2026-11-06']],
            'reversed' => [['from' => '2026-11-06', 'to' => '2026-11-01']],
            'longer than three months' => [['from' => '2026-08-05', 'to' => '2026-11-06']],
            'in the future' => [['from' => '2026-11-01', 'to' => '2026-11-30']],
        ];
    }

    public function test_exactly_three_months_is_accepted(): void
    {
        $this->postJson('/api/workspace/tools/recalculate', $this->range('2026-08-06', '2026-11-06'))->assertStatus(202);
    }

    public function test_the_command_recalculates_an_explicit_range_inline(): void
    {
        $this->artisan('stats:recalculate', ['workspace' => $this->workspace->id, '--from' => '2026-11-01', '--to' => '2026-11-06', '--now' => true])
            ->assertSuccessful();

        $this->assertSame(6, StatsDailyWorkspace::query()->withoutWorkspaceScope()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'data.recalculated')->count());
    }

    public function test_the_routine_run_queues_every_network_workspace_without_auditing(): void
    {
        Queue::fake();
        $other = Workspace::factory()->create();

        $this->artisan('stats:recalculate')->assertSuccessful();

        Queue::assertPushedOn('heavy', RecalculateStatsJob::class, fn (RecalculateStatsJob $job): bool => $job->workspaceId === $this->workspace->id);
        Queue::assertNotPushed(RecalculateStatsJob::class, fn (RecalculateStatsJob $job): bool => $job->workspaceId === $other->id);
        $this->assertSame(0, AuditLog::query()->where('action', 'data.recalculated')->count());
    }

    public function test_the_command_rejects_half_a_range_and_unknown_workspaces(): void
    {
        $this->artisan('stats:recalculate', ['--from' => '2026-11-01'])->assertExitCode(2);
        $this->artisan('stats:recalculate', ['--from' => '2026-11-06', '--to' => '2026-11-01'])->assertExitCode(2);
        $this->artisan('stats:recalculate', ['workspace' => '00000000-0000-0000-0000-000000000000'])->assertFailed();
    }
}
