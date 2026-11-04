<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\DocStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\UnbilledChargeStatus;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\InvoiceCreatedNotification;
use App\Services\Auth\TwoFactorService;
use App\Services\Finance\InvoiceLifecycleService;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;

class InvoiceLifecycleTest extends InvoiceV2TestCase
{
    private const BASE = '/api/workspace/invoices';

    public function test_owner_creates_a_draft_with_computed_totals_and_no_number(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        Sanctum::actingAs($owner);

        $this->postJson(self::BASE.'/drafts', $this->draftPayload($customer, null, ['tax_rate' => '15.00']))
            ->assertCreated()
            ->assertJsonPath('data.doc_status', 'draft')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.invoice_number', null)
            ->assertJsonCount(2, 'data.lines');

        $invoice = Invoice::query()->firstOrFail();
        $this->assertEquals(33.00, (float) $invoice->subtotal);
        $this->assertEquals(4.95, (float) $invoice->tax_amount);
        $this->assertEquals(37.95, (float) $invoice->total);
        $this->assertEquals(37.95, (float) $invoice->amount);
        $this->assertSame(InvoiceStatus::Pending, $invoice->status);
    }

    public function test_draft_validation_rejects_bad_money_and_foreign_customers(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        Sanctum::actingAs($owner);

        $this->postJson(self::BASE.'/drafts', $this->draftPayload($customer, [['description' => 'X', 'qty' => '1', 'unit_price' => '1.234']]))
            ->assertUnprocessable()->assertJsonValidationErrors('lines.0.unit_price');
        $this->postJson(self::BASE.'/drafts', $this->draftPayload($customer, []))
            ->assertUnprocessable()->assertJsonValidationErrors('lines');
        $this->postJson(self::BASE.'/drafts', $this->draftPayload(User::factory()->create()))
            ->assertUnprocessable();
    }

    public function test_issue_assigns_a_number_freezes_totals_and_is_audited(): void
    {
        Notification::fake();
        [$owner, $workspace] = $this->owningWorkspace();
        $draft = $this->draftFor($owner, $workspace);
        Sanctum::actingAs($owner);

        $this->postJson(self::BASE."/{$draft->id}/issue")
            ->assertOk()
            ->assertJsonPath('data.doc_status', 'issued')
            ->assertJsonPath('data.status', 'pending');

        $invoice = $draft->fresh();
        $this->assertMatchesRegularExpression('/-INV-000001$/', $invoice->invoice_number);
        $this->assertNotNull($invoice->issued_at);
        $this->assertTrue(AuditLog::query()->where('action', 'finance.invoice.issued')->exists());
        Notification::assertSentTo($draft->customer, InvoiceCreatedNotification::class);
    }

    public function test_numbers_are_consecutive_per_workspace(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $first = $this->issuedFor($owner, $workspace);
        $second = $this->issuedFor($owner, $workspace);
        [$otherOwner, $otherWorkspace] = $this->owningWorkspace();
        $other = $this->issuedFor($otherOwner, $otherWorkspace);

        $this->assertStringEndsWith('000001', $first->invoice_number);
        $this->assertStringEndsWith('000002', $second->invoice_number);
        $this->assertStringEndsWith('000001', $other->invoice_number);
        $this->assertNotSame($first->invoice_number, $other->invoice_number);
    }

    public function test_issue_requires_a_draft_with_lines(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $issued = $this->issuedFor($owner, $workspace);
        Sanctum::actingAs($owner);

        $this->postJson(self::BASE."/{$issued->id}/issue")->assertStatus(409);

        $empty = $this->draftFor($owner, $workspace);
        $empty->lines()->getQuery()->delete();
        $this->postJson(self::BASE."/{$empty->id}/issue")->assertUnprocessable();
    }

    public function test_issuing_consumes_unbilled_charges_and_voiding_releases_them(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $charge = $this->openCharge($workspace, $customer, '25.00');
        Sanctum::actingAs($owner);

        $response = $this->postJson(self::BASE.'/drafts', $this->draftPayload($customer, [
            ['unbilled_charge_id' => $charge->id],
            ['description' => 'Extra', 'qty' => '1', 'unit_price' => '5.00'],
        ]))->assertCreated();
        $invoiceId = $response->json('data.id');

        $this->assertSame(UnbilledChargeStatus::Open, $charge->fresh()->status);
        $this->postJson(self::BASE."/{$invoiceId}/issue")->assertOk();

        $charge = $charge->fresh();
        $this->assertSame(UnbilledChargeStatus::Invoiced, $charge->status);
        $this->assertNotNull($charge->invoice_line_id);
        $this->assertTrue(AuditLog::query()->where('action', 'finance.unbilled.invoiced')->exists());

        $this->postJson(self::BASE."/{$invoiceId}/void", ['reason' => 'Billed twice'])->assertOk();

        $charge = $charge->fresh();
        $this->assertSame(UnbilledChargeStatus::Open, $charge->status);
        $this->assertNull($charge->invoice_line_id);
    }

    public function test_a_charge_of_another_customer_or_already_billed_is_rejected(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $foreign = $this->openCharge($workspace, $this->subscriber($workspace));
        Sanctum::actingAs($owner);

        $this->postJson(self::BASE.'/drafts', $this->draftPayload($customer, [['unbilled_charge_id' => $foreign->id]]))
            ->assertUnprocessable();
        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_void_keeps_rows_and_payments_and_is_audited_with_the_reason(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->issuedFor($owner, $workspace);
        $invoice->payments()->create(['amount' => 5, 'paid_at' => now()]);
        Sanctum::actingAs($owner);

        $this->postJson(self::BASE."/{$invoice->id}/void", ['reason' => 'Customer cancelled'])
            ->assertOk()
            ->assertJsonPath('data.doc_status', 'void')
            ->assertJsonPath('data.status', 'cancelled');

        $voided = $invoice->fresh();
        $this->assertSame(DocStatus::Void, $voided->doc_status);
        $this->assertNotNull($voided->voided_at);
        $this->assertSame('Customer cancelled', $voided->void_reason);
        $this->assertSame(2, $voided->lines()->count());
        $this->assertSame(1, $voided->payments()->count());
        $this->assertSame('Customer cancelled', AuditLog::query()->where('action', 'finance.invoice.voided')->value('reason'));

        $this->postJson(self::BASE."/{$invoice->id}/void", ['reason' => 'Again'])->assertStatus(409);
        $this->postJson(self::BASE."/{$invoice->id}/void", [])->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    public function test_deleting_a_draft_removes_it_but_deleting_an_issued_invoice_voids_it(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $draft = $this->draftFor($owner, $workspace);
        $issued = $this->issuedFor($owner, $workspace);
        $issued->payments()->create(['amount' => 5, 'paid_at' => now()]);
        Sanctum::actingAs($owner);

        $this->deleteJson(self::BASE."/{$draft->id}")->assertOk();
        $this->assertDatabaseMissing('invoices', ['id' => $draft->id]);

        $this->deleteJson(self::BASE."/{$issued->id}")->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->deleteJson(self::BASE."/{$issued->id}", ['reason' => 'Mistake'])->assertOk();

        $this->assertDatabaseHas('invoices', ['id' => $issued->id, 'doc_status' => 'void', 'status' => 'cancelled']);
        $this->assertSame(2, $issued->lines()->count());
        $this->assertSame(1, $issued->payments()->count());
    }

    public function test_issue_and_void_need_a_recent_two_factor_check(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $owner->forceFill(['two_factor_secret' => 'SECRET', 'two_factor_confirmed_at' => now()])->save();
        $draft = $this->draftFor($owner, $workspace);
        $issued = $this->issuedFor($owner, $workspace);
        Sanctum::actingAs($owner);

        $this->postJson(self::BASE."/{$draft->id}/issue")->assertForbidden()->assertJsonPath('code', 'two_factor_required');
        $this->postJson(self::BASE."/{$issued->id}/void", ['reason' => 'No way'])->assertForbidden()->assertJsonPath('code', 'two_factor_required');
        $this->deleteJson(self::BASE."/{$issued->id}", ['reason' => 'No way'])->assertForbidden()->assertJsonPath('code', 'two_factor_required');
        $this->assertSame(DocStatus::Draft, $draft->fresh()->doc_status);

        app(TwoFactorService::class)->markVerified($owner);

        $this->postJson(self::BASE."/{$draft->id}/issue")->assertOk();
        $this->postJson(self::BASE."/{$issued->id}/void", ['reason' => 'Fine now'])->assertOk();
    }

    public function test_drafts_are_edited_until_issued_and_hidden_from_the_member(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $member = $this->subscriber($workspace);
        $draft = $this->draftFor($owner, $workspace, $member);
        Sanctum::actingAs($owner);

        $this->putJson(self::BASE."/{$draft->id}/draft", $this->draftPayload($member, [['description' => 'Only', 'qty' => '1', 'unit_price' => '7.00']]))
            ->assertOk()->assertJsonCount(1, 'data.lines');
        $this->assertEquals(7.00, (float) $draft->fresh()->total);

        Sanctum::actingAs($member);
        $this->getJson('/api/member/invoices')->assertOk()->assertJsonCount(0, 'data.invoices');
        $this->getJson("/api/member/invoices/{$draft->id}")->assertNotFound();

        Sanctum::actingAs($owner);
        $this->postJson(self::BASE."/{$draft->id}/issue")->assertOk();

        Sanctum::actingAs($member);
        $this->getJson('/api/member/invoices')->assertOk()->assertJsonCount(1, 'data.invoices');
    }

    public function test_the_flag_off_hides_the_v2_endpoints_and_keeps_legacy_deletion(): void
    {
        [$owner, $workspace] = $this->owningWorkspace(financeV2: false);
        $customer = $this->subscriber($workspace);
        Sanctum::actingAs($owner);

        $this->postJson(self::BASE.'/drafts', $this->draftPayload($customer))->assertNotFound();

        $invoice = Invoice::factory()->create(['subscription_id' => $customer->subscriptions()->value('id')]);
        $this->deleteJson(self::BASE."/{$invoice->id}")->assertOk();
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    public function test_another_workspaces_invoice_is_not_found(): void
    {
        [$owner] = $this->owningWorkspace();
        [$otherOwner, $otherWorkspace] = $this->owningWorkspace();
        $foreign = $this->draftFor($otherOwner, $otherWorkspace);
        Sanctum::actingAs($owner);

        $this->postJson(self::BASE."/{$foreign->id}/issue")->assertNotFound();
        $this->putJson(self::BASE."/{$foreign->id}/draft", $this->draftPayload(User::factory()->create()))->assertNotFound();
        $this->assertSame(PaymentStatus::Unpaid, $foreign->fresh()->payment_status);
    }

    public function test_legacy_payment_flows_keep_the_new_statuses_in_sync(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->issuedFor($owner, $workspace);
        Sanctum::actingAs($owner);

        $this->putJson(self::BASE."/{$invoice->id}/pay")->assertOk();

        $paid = $invoice->fresh();
        $this->assertSame(InvoiceStatus::Paid, $paid->status);
        $this->assertSame(PaymentStatus::Paid, $paid->payment_status);
        $this->assertSame(DocStatus::Issued, $paid->doc_status);
    }

    /** @return array<string, array{0: string, 1: string, 2: array<string, mixed>}> */
    public static function paymentEndpoints(): array
    {
        return [
            'pay' => ['put', '/pay', []],
            'partial payment' => ['put', '/partial-payment', ['amount' => '1.00']],
            'approve receipt' => ['put', '/approve-receipt', []],
            'remind' => ['post', '/remind', []],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    #[DataProvider('paymentEndpoints')]
    public function test_payment_flows_are_rejected_on_a_draft(string $method, string $path, array $payload): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $draft = $this->draftFor($owner, $workspace);
        Sanctum::actingAs($owner);

        $this->{$method.'Json'}(self::BASE."/{$draft->id}{$path}", $payload)->assertStatus(409);

        $fresh = $draft->fresh();
        $this->assertSame(DocStatus::Draft, $fresh->doc_status);
        $this->assertSame(InvoiceStatus::Pending, $fresh->status);
        $this->assertSame(PaymentStatus::Unpaid, $fresh->payment_status);
    }

    /** @param  array<string, mixed>  $payload */
    #[DataProvider('paymentEndpoints')]
    public function test_payment_flows_are_rejected_on_a_void_invoice(string $method, string $path, array $payload): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = app(InvoiceLifecycleService::class)->void($this->issuedFor($owner, $workspace), 'Mistake', $owner);
        Sanctum::actingAs($owner);

        $this->{$method.'Json'}(self::BASE."/{$invoice->id}{$path}", $payload)->assertStatus(409);

        $fresh = $invoice->fresh();
        $this->assertSame(DocStatus::Void, $fresh->doc_status);
        $this->assertNotSame(InvoiceStatus::Paid, $fresh->status);
    }
}
