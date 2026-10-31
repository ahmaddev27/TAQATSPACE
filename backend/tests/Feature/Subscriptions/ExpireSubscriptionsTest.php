<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Enums\SeatStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Seat;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SubscriptionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    private function subscription(string $status, ?string $endDate, bool $withSeat = false): Subscription
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->freelancer()->create();
        $seat = $withSeat
            ? Seat::factory()->create([
                'workspace_id' => $workspace->id,
                'status' => SeatStatus::Occupied,
                'assigned_member_id' => $member->id,
            ])
            : null;

        return Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'seat_id' => $seat?->id,
            'status' => $status,
            'end_date' => $endDate,
        ]);
    }

    public function test_ended_active_subscription_expires_and_frees_its_seat(): void
    {
        $subscription = $this->subscription('active', now()->subDay()->toDateString(), true);
        $seatId = $subscription->seat_id;

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Expired, $subscription->refresh()->status);
        $this->assertNull($subscription->seat_id);
        $seat = Seat::findOrFail($seatId);
        $this->assertSame(SeatStatus::Available, $seat->status);
        $this->assertNull($seat->assigned_member_id);
    }

    public function test_subscriptions_not_due_are_unchanged(): void
    {
        $today = $this->subscription('active', now()->toDateString());
        $future = $this->subscription('active', now()->addDay()->toDateString());
        $noEnd = $this->subscription('active', null);
        $others = collect(['pending', 'suspended', 'cancelled'])
            ->map(fn (string $status) => $this->subscription($status, now()->subDays(5)->toDateString()));

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Active, $today->refresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $future->refresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $noEnd->refresh()->status);
        foreach ($others as $other) {
            $this->assertSame($other->status, $other->refresh()->status);
        }
    }

    public function test_running_twice_is_idempotent(): void
    {
        $this->subscription('active', now()->subDay()->toDateString(), true);

        $this->assertSame(1, app(SubscriptionLifecycleService::class)->expireDue());
        $this->assertSame(0, app(SubscriptionLifecycleService::class)->expireDue());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $subscription = $this->subscription('active', now()->subDay()->toDateString(), true);

        $this->artisan('subscriptions:expire --dry-run')
            ->expectsOutputToContain('1 subscription(s) would be expired')
            ->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Active, $subscription->refresh()->status);
        $this->assertNotNull($subscription->seat_id);
    }

    public function test_expire_due_returns_the_expired_count(): void
    {
        $this->subscription('active', now()->subDays(2)->toDateString());
        $this->subscription('active', now()->subDay()->toDateString(), true);
        $this->subscription('active', now()->addDay()->toDateString());

        $this->assertSame(2, app(SubscriptionLifecycleService::class)->expireDue());
    }

    public function test_command_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('subscriptions:expire')->assertSuccessful();
    }
}
