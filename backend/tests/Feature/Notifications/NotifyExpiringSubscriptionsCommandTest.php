<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Notifications\SubscriptionExpiringNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * `subscriptions:notify-expiring` notifies members whose active subscription
 * ends exactly `--days` days from today.
 */
class NotifyExpiringSubscriptionsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function activeSubscriptionEndingIn(int $days): Subscription
    {
        return Subscription::factory()->create([
            'workspace_id' => Workspace::factory(),
            'status' => SubscriptionStatus::Active,
            'end_date' => Carbon::today()->addDays($days),
        ]);
    }

    public function test_command_notifies_member_expiring_on_the_target_day(): void
    {
        Notification::fake();
        $subscription = $this->activeSubscriptionEndingIn(3);

        $this->artisan('subscriptions:notify-expiring', ['--days' => 3])
            ->assertExitCode(0);

        Notification::assertSentTo(
            $subscription->member,
            SubscriptionExpiringNotification::class,
            fn (SubscriptionExpiringNotification $n) => $n->daysLeft === 3,
        );
    }

    public function test_command_skips_subscriptions_ending_on_other_days(): void
    {
        Notification::fake();
        $this->activeSubscriptionEndingIn(5);

        $this->artisan('subscriptions:notify-expiring', ['--days' => 3])
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_command_ignores_non_active_subscriptions(): void
    {
        Notification::fake();
        Subscription::factory()->create([
            'workspace_id' => Workspace::factory(),
            'status' => SubscriptionStatus::Cancelled,
            'end_date' => Carbon::today()->addDays(3),
        ]);

        $this->artisan('subscriptions:notify-expiring', ['--days' => 3])
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_command_defaults_to_three_days(): void
    {
        Notification::fake();
        $subscription = $this->activeSubscriptionEndingIn(3);

        $this->artisan('subscriptions:notify-expiring')->assertExitCode(0);

        Notification::assertSentTo($subscription->member, SubscriptionExpiringNotification::class);
    }
}
