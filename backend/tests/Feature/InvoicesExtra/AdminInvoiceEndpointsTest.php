<?php

declare(strict_types=1);

namespace Tests\Feature\InvoicesExtra;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\InvoicePaidNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminInvoiceEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function billingAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        Permission::findOrCreate('manage_billing', 'web');
        $admin->givePermissionTo('manage_billing');

        return $admin;
    }

    private function invoice(array $attrs = []): Invoice
    {
        $subscription = Subscription::factory()->create(['status' => SubscriptionStatus::Active]);

        return Invoice::factory()->create(array_merge([
            'subscription_id' => $subscription->id,
            'amount' => '40.00',
        ], $attrs));
    }

    public function test_admin_mark_paid_records_payment(): void
    {
        Notification::fake();
        $admin = $this->billingAdmin();
        $invoice = $this->invoice(['status' => InvoiceStatus::Pending]);
        $member = $invoice->subscription->member;

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/invoices/{$invoice->id}/mark-paid", [
            'paid_at' => '2026-06-20',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', InvoiceStatus::Paid->value);

        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
        Notification::assertSentTo($member, InvoicePaidNotification::class);
    }

    public function test_admin_mark_paid_with_receipt_stores_file(): void
    {
        Notification::fake();
        Storage::fake('public');
        $admin = $this->billingAdmin();
        $invoice = $this->invoice(['status' => InvoiceStatus::Pending]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/invoices/{$invoice->id}/mark-paid", [
            'receipt' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertOk();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertNotNull($invoice->receipt_path);
        Storage::disk('public')->assertExists($invoice->receipt_path);
    }

    public function test_admin_mark_paid_422_when_already_paid(): void
    {
        Notification::fake();
        $admin = $this->billingAdmin();
        $invoice = $this->invoice(['status' => InvoiceStatus::Paid]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/invoices/{$invoice->id}/mark-paid")->assertStatus(422);
    }

    public function test_admin_mark_unpaid_reverts_to_pending(): void
    {
        $admin = $this->billingAdmin();
        $invoice = $this->invoice(['status' => InvoiceStatus::Paid, 'paid_at' => now()]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/invoices/{$invoice->id}/mark-unpaid")
            ->assertOk()
            ->assertJsonPath('data.status', InvoiceStatus::Pending->value);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Pending, $invoice->status);
        $this->assertNull($invoice->paid_at);
    }

    public function test_admin_attach_receipt_stores_file_and_marks_paid(): void
    {
        Notification::fake();
        Storage::fake('public');
        $admin = $this->billingAdmin();
        $invoice = $this->invoice(['status' => InvoiceStatus::Pending]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/invoices/{$invoice->id}/receipt", [
            'receipt' => UploadedFile::fake()->image('proof.png'),
        ])->assertOk();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertNotNull($invoice->receipt_path);
        Storage::disk('public')->assertExists($invoice->receipt_path);
    }

    public function test_admin_routes_reject_unauthenticated(): void
    {
        $invoice = $this->invoice();

        $this->putJson("/api/admin/invoices/{$invoice->id}/mark-paid")->assertUnauthorized();
    }

    public function test_admin_routes_reject_a_non_admin_role(): void
    {
        $owner = User::factory()->owner()->create();
        $invoice = $this->invoice();

        Sanctum::actingAs($owner);

        $this->putJson("/api/admin/invoices/{$invoice->id}/mark-paid")->assertForbidden();
    }

    public function test_admin_without_billing_permission_is_forbidden(): void
    {
        $admin = User::factory()->admin()->create();
        $invoice = $this->invoice();

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/invoices/{$invoice->id}/mark-paid")->assertForbidden();
    }
}
