<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Enums\PlanType;
use App\Enums\SeatStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Seat;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\BookingService;
use App\Services\OwnerSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit-level coverage of the owner subscription lifecycle service: the
 * filterable roster, renewal (period extension + next invoice), and cancel.
 */
class OwnerSubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): OwnerSubscriptionService
    {
        return app(OwnerSubscriptionService::class);
    }

    private function workspace(): Workspace
    {
        return Workspace::factory()->create();
    }

    private function memberNamed(string $name, string $email): User
    {
        return User::factory()->freelancer()->create(['name' => $name, 'email' => $email]);
    }

    // ---- Roster: filtering, search, sorting ----

    public function test_roster_only_returns_subscriptions_for_the_given_workspace(): void
    {
        $workspace = $this->workspace();
        $other = $this->workspace();

        Subscription::factory()->count(2)->create(['workspace_id' => $workspace->id]);
        Subscription::factory()->create(['workspace_id' => $other->id]);

        $page = $this->service()->paginateForWorkspace($workspace, []);

        $this->assertSame(2, $page->total());
        foreach ($page->items() as $subscription) {
            $this->assertSame($workspace->id, $subscription->workspace_id);
        }
    }

    public function test_status_filter_narrows_to_one_status_and_all_is_a_no_op(): void
    {
        $workspace = $this->workspace();
        Subscription::factory()->count(2)->create([
            'workspace_id' => $workspace->id,
            'status' => SubscriptionStatus::Active->value,
        ]);
        Subscription::factory()->cancelled()->create(['workspace_id' => $workspace->id]);

        $active = $this->service()->paginateForWorkspace($workspace, ['status' => 'active']);
        $this->assertSame(2, $active->total());

        $all = $this->service()->paginateForWorkspace($workspace, ['status' => 'all']);
        $this->assertSame(3, $all->total());
    }

    public function test_unknown_status_value_is_ignored(): void
    {
        $workspace = $this->workspace();
        Subscription::factory()->count(2)->create(['workspace_id' => $workspace->id]);

        $page = $this->service()->paginateForWorkspace($workspace, ['status' => 'not-a-status']);

        $this->assertSame(2, $page->total());
    }

    public function test_search_matches_member_name_and_email(): void
    {
        $workspace = $this->workspace();
        $alice = $this->memberNamed('Alice Wonder', 'alice@mail.ps');
        $bob = $this->memberNamed('Bob Builder', 'bob@mail.ps');

        Subscription::factory()->create(['workspace_id' => $workspace->id, 'member_id' => $alice->id]);
        Subscription::factory()->create(['workspace_id' => $workspace->id, 'member_id' => $bob->id]);

        $byName = $this->service()->paginateForWorkspace($workspace, ['search' => 'Wonder']);
        $this->assertSame(1, $byName->total());
        $this->assertSame($alice->id, $byName->items()[0]->member_id);

        $byEmail = $this->service()->paginateForWorkspace($workspace, ['search' => 'bob@mail']);
        $this->assertSame(1, $byEmail->total());
        $this->assertSame($bob->id, $byEmail->items()[0]->member_id);
    }

    public function test_sort_by_member_name_ascending(): void
    {
        $workspace = $this->workspace();
        $zoe = $this->memberNamed('Zoe Last', 'zoe@mail.ps');
        $amy = $this->memberNamed('Amy First', 'amy@mail.ps');

        Subscription::factory()->create(['workspace_id' => $workspace->id, 'member_id' => $zoe->id]);
        Subscription::factory()->create(['workspace_id' => $workspace->id, 'member_id' => $amy->id]);

        $page = $this->service()->paginateForWorkspace($workspace, [
            'sort' => 'name',
            'direction' => 'asc',
        ]);

        $this->assertSame($amy->id, $page->items()[0]->member_id);
        $this->assertSame($zoe->id, $page->items()[1]->member_id);
    }

    public function test_per_page_is_clamped_to_the_allowed_maximum(): void
    {
        $workspace = $this->workspace();
        Subscription::factory()->create(['workspace_id' => $workspace->id]);

        $page = $this->service()->paginateForWorkspace($workspace, ['per_page' => 9999]);

        $this->assertSame(200, $page->perPage());
    }

    // ---- Renew ----

    public function test_renew_extends_a_due_monthly_subscription_by_one_month_and_reactivates_it(): void
    {
        $workspace = $this->workspace();
        $endDate = Carbon::today()->subDays(2);

        $subscription = Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'plan_type' => PlanType::Monthly->value,
            'status' => SubscriptionStatus::Expired->value,
            'end_date' => $endDate->toDateString(),
        ]);

        $renewed = $this->service()->renew($workspace, $subscription);

        $this->assertSame(SubscriptionStatus::Active, $renewed->status);
        $this->assertNull($renewed->cancelled_at);
        // Lapsed term resumes from today, +1 month.
        $this->assertSame(
            Carbon::today()->addMonth()->toDateString(),
            $renewed->end_date->toDateString(),
        );
    }

    public function test_renew_daily_plan_extends_by_one_day(): void
    {
        $workspace = $this->workspace();

        $subscription = Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'plan_type' => PlanType::Daily->value,
            'status' => SubscriptionStatus::Expired->value,
            'end_date' => Carbon::today()->subDay()->toDateString(),
        ]);

        $renewed = $this->service()->renew($workspace, $subscription);

        $this->assertSame(
            Carbon::today()->addDay()->toDateString(),
            $renewed->end_date->toDateString(),
        );
    }

    public function test_renew_raises_the_next_pending_invoice(): void
    {
        $workspace = $this->workspace();
        $subscription = Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SubscriptionStatus::Expired->value,
            'end_date' => Carbon::today()->subDay()->toDateString(),
        ]);

        $before = $subscription->invoices()->count();
        $this->service()->renew($workspace, $subscription);

        $this->assertSame($before + 1, $subscription->invoices()->count());
    }

    public function test_renew_rejects_a_cancelled_subscription(): void
    {
        $workspace = $this->workspace();
        $subscription = Subscription::factory()->cancelled()->create([
            'workspace_id' => $workspace->id,
            'end_date' => Carbon::today()->subDay()->toDateString(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->service()->renew($workspace, $subscription);
    }

    public function test_renew_rejects_a_term_that_has_not_reached_its_end_date(): void
    {
        $workspace = $this->workspace();
        $subscription = Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SubscriptionStatus::Active->value,
            'end_date' => Carbon::today()->addMonth()->toDateString(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->service()->renew($workspace, $subscription);
    }

    public function test_renew_rejects_a_subscription_from_another_workspace(): void
    {
        $workspace = $this->workspace();
        $foreign = Subscription::factory()->create([
            'end_date' => Carbon::today()->subDay()->toDateString(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->service()->renew($workspace, $foreign);
    }

    // ---- Cancel ----

    public function test_cancel_marks_the_subscription_cancelled_and_frees_its_seat(): void
    {
        $workspace = $this->workspace();
        $seat = Seat::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SeatStatus::Occupied->value,
        ]);
        $subscription = Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'seat_id' => $seat->id,
            'status' => SubscriptionStatus::Active->value,
        ]);

        $cancelled = $this->service()->cancel($workspace, $subscription, app(BookingService::class));

        $this->assertSame(SubscriptionStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);

        $seat->refresh();
        $this->assertSame(SeatStatus::Available, $seat->status);
        $this->assertNull($seat->assigned_member_id);
    }

    public function test_cancel_rejects_a_subscription_from_another_workspace(): void
    {
        $workspace = $this->workspace();
        $foreign = Subscription::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->service()->cancel($workspace, $foreign, app(BookingService::class));
    }
}
