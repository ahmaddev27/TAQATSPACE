<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Notifications\InvoiceCreatedNotification;
use App\Notifications\InvoiceOverdueNotification;
use App\Notifications\SubscriptionExpiringNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The scheduler-driven billing commands: overdue flagging, overdue re-reminders,
 * expiring-subscription notices, and monthly invoice generation.
 */
class ScheduledCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function activeSubscription(array $attributes = []): Subscription
    {
        return Subscription::factory()->create($attributes + [
            'status' => SubscriptionStatus::Active->value,
        ]);
    }

    // ---- invoices:mark-overdue -------------------------------------------

    public function test_mark_overdue_flips_eligible_pending_invoices_and_notifies(): void
    {
        Notification::fake();

        $pastDue = Invoice::factory()->create([
            'status' => InvoiceStatus::Pending->value,
            'due_date' => Carbon::today()->subDays(5)->toDateString(),
        ]);

        $futureDue = Invoice::factory()->create([
            'status' => InvoiceStatus::Pending->value,
            'due_date' => Carbon::today()->addDays(5)->toDateString(),
        ]);

        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        $this->assertSame(InvoiceStatus::Overdue, $pastDue->refresh()->status);
        $this->assertSame(InvoiceStatus::Pending, $futureDue->refresh()->status);

        // Member + owner each get an overdue notice for the flipped invoice.
        Notification::assertSentTo(
            $pastDue->subscription->member,
            InvoiceOverdueNotification::class,
        );
        Notification::assertSentTo(
            $pastDue->subscription->workspace->owner,
            InvoiceOverdueNotification::class,
        );
        Notification::assertNothingSentTo($futureDue->subscription->member);
    }

    // ---- invoices:remind-overdue -----------------------------------------

    public function test_remind_overdue_sends_then_respects_the_cooldown(): void
    {
        Notification::fake();

        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::Overdue->value,
            'due_date' => Carbon::today()->subDays(10)->toDateString(),
        ]);

        $this->artisan('invoices:remind-overdue')->assertSuccessful();
        Notification::assertSentToTimes(
            $invoice->subscription->member,
            InvoiceOverdueNotification::class,
            1,
        );

        // A second immediate run is throttled by the per-invoice cooldown cache.
        $this->artisan('invoices:remind-overdue')->assertSuccessful();
        Notification::assertSentToTimes(
            $invoice->subscription->member,
            InvoiceOverdueNotification::class,
            1,
        );
    }

    // ---- subscriptions:notify-expiring -----------------------------------

    public function test_notify_expiring_targets_only_subscriptions_ending_on_the_target_day(): void
    {
        Notification::fake();

        $days = 3;
        $target = Carbon::today()->addDays($days)->toDateString();

        $expiring = $this->activeSubscription(['end_date' => $target]);
        $other = $this->activeSubscription([
            'end_date' => Carbon::today()->addDays($days + 1)->toDateString(),
        ]);

        $this->artisan('subscriptions:notify-expiring', ['--days' => $days])->assertSuccessful();

        Notification::assertSentTo($expiring->member, SubscriptionExpiringNotification::class);
        Notification::assertNothingSentTo($other->member);
    }

    // ---- invoices:generate-monthly ---------------------------------------

    public function test_generate_monthly_creates_one_invoice_per_active_subscription_and_is_idempotent(): void
    {
        Notification::fake();

        $subscription = $this->activeSubscription();

        $this->artisan('invoices:generate-monthly')->assertSuccessful();

        $this->assertSame(1, Invoice::where('subscription_id', $subscription->id)->count());
        Notification::assertSentToTimes(
            $subscription->member,
            InvoiceCreatedNotification::class,
            1,
        );

        // A second run in the same billing month must not duplicate the invoice.
        $this->artisan('invoices:generate-monthly')->assertSuccessful();

        $this->assertSame(1, Invoice::where('subscription_id', $subscription->id)->count());
        Notification::assertSentToTimes(
            $subscription->member,
            InvoiceCreatedNotification::class,
            1,
        );
    }
}
