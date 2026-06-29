<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Notifications\InvoiceOverdueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * `invoices:remind-overdue` re-chases members about invoices still unpaid past
 * their due date (overdue or partially paid), throttled per invoice.
 */
class RemindOverdueInvoicesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function unpaidPastDue(InvoiceStatus $status): Invoice
    {
        $workspace = Workspace::factory()->create();
        $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id]);

        return Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => $status,
            'due_date' => Carbon::today()->subDays(10),
        ]);
    }

    public function test_command_reminds_overdue_member(): void
    {
        Notification::fake();
        $invoice = $this->unpaidPastDue(InvoiceStatus::Overdue);

        $this->artisan('invoices:remind-overdue')
            ->expectsOutputToContain('Sent 1 overdue reminder(s).')
            ->assertExitCode(0);

        Notification::assertSentTo(
            $invoice->subscription->member,
            InvoiceOverdueNotification::class,
        );
    }

    public function test_command_reminds_partially_paid_member(): void
    {
        Notification::fake();
        $invoice = $this->unpaidPastDue(InvoiceStatus::PartiallyPaid);

        $this->artisan('invoices:remind-overdue')
            ->expectsOutputToContain('Sent 1 overdue reminder(s).')
            ->assertExitCode(0);

        Notification::assertSentTo(
            $invoice->subscription->member,
            InvoiceOverdueNotification::class,
        );
    }

    public function test_command_respects_cooldown_and_skips_recently_reminded(): void
    {
        Notification::fake();
        $invoice = $this->unpaidPastDue(InvoiceStatus::Overdue);
        Cache::put("invoice:{$invoice->id}:overdue_reminder", true, Carbon::now()->addDays(7));

        $this->artisan('invoices:remind-overdue')
            ->expectsOutputToContain('Sent 0 overdue reminder(s).')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_command_skips_paid_invoices(): void
    {
        Notification::fake();
        Invoice::factory()->paid()->create(['due_date' => Carbon::today()->subDays(10)]);

        $this->artisan('invoices:remind-overdue')
            ->expectsOutputToContain('Sent 0 overdue reminder(s).')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }
}
