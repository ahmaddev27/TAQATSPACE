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
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * `invoices:mark-overdue` flags pending invoices past their due date and
 * notifies both the member and the workspace owner.
 */
class MarkOverdueInvoicesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function pastDueInvoice(): Invoice
    {
        $workspace = Workspace::factory()->create();
        $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id]);

        return Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => InvoiceStatus::Pending,
            'due_date' => Carbon::today()->subDays(5),
        ]);
    }

    public function test_command_flags_overdue_and_notifies_member_and_owner(): void
    {
        Notification::fake();
        $invoice = $this->pastDueInvoice();

        $this->artisan('invoices:mark-overdue')
            ->expectsOutputToContain('Flagged 1 invoice(s) as overdue.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Overdue->value,
        ]);

        $member = $invoice->subscription->member;
        $owner = $invoice->subscription->workspace->owner;

        Notification::assertSentTo($member, InvoiceOverdueNotification::class,
            fn (InvoiceOverdueNotification $n) => $n->audience === 'member');
        Notification::assertSentTo($owner, InvoiceOverdueNotification::class,
            fn (InvoiceOverdueNotification $n) => $n->audience === 'owner');
    }

    public function test_command_ignores_invoices_not_yet_due(): void
    {
        Notification::fake();
        $workspace = Workspace::factory()->create();
        $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id]);
        Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => InvoiceStatus::Pending,
            'due_date' => Carbon::today()->addDays(5),
        ]);

        $this->artisan('invoices:mark-overdue')
            ->expectsOutputToContain('Flagged 0 invoice(s) as overdue.')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_command_does_not_touch_already_paid_invoices(): void
    {
        Notification::fake();
        $invoice = Invoice::factory()->paid()->create([
            'due_date' => Carbon::today()->subDays(10),
        ]);

        $this->artisan('invoices:mark-overdue')->assertExitCode(0);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Paid->value,
        ]);
        Notification::assertNothingSent();
    }
}
