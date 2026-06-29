<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Notifications\InvoiceCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * `invoices:generate-monthly` raises one pending invoice per active
 * subscription for the current month (idempotent) and notifies the member.
 */
class GenerateMonthlyInvoicesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_one_invoice_per_active_subscription_and_notifies(): void
    {
        Notification::fake();
        $subscription = Subscription::factory()->create([
            'workspace_id' => Workspace::factory(),
            'status' => SubscriptionStatus::Active,
            'end_date' => null,
        ]);

        $this->artisan('invoices:generate-monthly')
            ->expectsOutputToContain('Created 1 invoice(s).')
            ->assertExitCode(0);

        $this->assertDatabaseHas('invoices', ['subscription_id' => $subscription->id]);
        Notification::assertSentTo($subscription->member, InvoiceCreatedNotification::class);
    }

    public function test_command_is_idempotent_within_the_same_month(): void
    {
        Notification::fake();
        Subscription::factory()->create([
            'workspace_id' => Workspace::factory(),
            'status' => SubscriptionStatus::Active,
            'end_date' => null,
        ]);

        $this->artisan('invoices:generate-monthly')->assertExitCode(0);
        $this->assertSame(1, Invoice::count());

        $this->artisan('invoices:generate-monthly')
            ->expectsOutputToContain('Created 0 invoice(s).')
            ->assertExitCode(0);

        $this->assertSame(1, Invoice::count());
    }

    public function test_command_skips_inactive_subscriptions(): void
    {
        Notification::fake();
        Subscription::factory()->create([
            'workspace_id' => Workspace::factory(),
            'status' => SubscriptionStatus::Cancelled,
            'end_date' => null,
        ]);

        $this->artisan('invoices:generate-monthly')
            ->expectsOutputToContain('Created 0 invoice(s).')
            ->assertExitCode(0);

        $this->assertSame(0, Invoice::count());
    }

    public function test_command_skips_subscriptions_already_ended(): void
    {
        Notification::fake();
        Subscription::factory()->create([
            'workspace_id' => Workspace::factory(),
            'status' => SubscriptionStatus::Active,
            'end_date' => Carbon::today()->subMonth(),
        ]);

        $this->artisan('invoices:generate-monthly')
            ->expectsOutputToContain('Created 0 invoice(s).')
            ->assertExitCode(0);

        $this->assertSame(0, Invoice::count());
    }

    public function test_generated_invoice_number_uses_workspace_prefix(): void
    {
        Notification::fake();
        $workspace = Workspace::factory()->create(['name' => 'Acme Space']);
        Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SubscriptionStatus::Active,
            'end_date' => null,
        ]);

        $this->artisan('invoices:generate-monthly')->assertExitCode(0);

        $invoice = Invoice::first();
        $this->assertStringStartsWith('ACMESP-'.Carbon::today()->year.'-', (string) $invoice->invoice_number);
    }
}
