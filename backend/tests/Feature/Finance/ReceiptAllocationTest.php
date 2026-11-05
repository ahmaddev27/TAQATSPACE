<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\FinancePermission;
use App\Enums\InvoiceStatus;
use App\Models\AuditLog;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Services\Auth\TwoFactorService;
use App\Services\Finance\InvoiceLifecycleService;
use App\Services\Finance\ReceiptService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class ReceiptAllocationTest extends ReceiptTestCase
{
    public function test_a_receipt_split_across_two_invoices_settles_each_by_its_balance(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $first = $this->invoiceOf($owner, $workspace, $customer, '600.00');
        $second = $this->invoiceOf($owner, $workspace, $customer, '700.00');
        Sanctum::actingAs($owner);

        $response = $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '1000.00', [
            'allocations' => [
                ['invoice_id' => $first->id, 'amount' => '600.00'],
                ['invoice_id' => $second->id, 'amount' => '400.00'],
            ],
        ]))->assertCreated();

        $response->assertJsonPath('data.allocated_amount', '1000.00')->assertJsonPath('data.unallocated_amount', '0.00');
        $this->assertStringContainsString('-REC-', $response->json('data.number'));

        $first = $first->fresh();
        $second = $second->fresh();
        $this->assertSame('paid', $first->payment_status->value);
        $this->assertSame(InvoiceStatus::Paid, $first->status);
        $this->assertEquals(600, $first->amount_paid);
        $this->assertNotNull($first->paid_at);
        $this->assertSame('partial', $second->payment_status->value);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $second->status);
        $this->assertEquals(400, $second->amount_paid);
        $this->assertNull($second->paid_at);
        $this->assertTrue(AuditLog::query()->where('action', 'finance.receipt.created')->exists());
    }

    public function test_an_allocation_above_the_outstanding_balance_is_rejected_and_nothing_is_stored(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        Sanctum::actingAs($owner);

        $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '500.00', [
            'allocations' => $this->allocation($invoice, '100.01'),
        ]))->assertUnprocessable();

        $this->assertSame(0, Receipt::query()->count());
        $this->assertEquals(0, $invoice->fresh()->amount_paid);
    }

    public function test_allocations_cannot_exceed_the_receipt_amount(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        Sanctum::actingAs($owner);

        $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '50.00', [
            'allocations' => $this->allocation($invoice, '60.00'),
        ]))->assertUnprocessable();
    }

    public function test_a_second_receipt_cannot_over_allocate_an_invoice(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        Sanctum::actingAs($owner);

        $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '70.00', ['allocations' => $this->allocation($invoice, '70.00')]))->assertCreated();
        $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '70.00', ['allocations' => $this->allocation($invoice, '70.00')]))
            ->assertUnprocessable();

        $this->assertEquals(70, $invoice->fresh()->amount_paid);
        $this->assertSame(1, Receipt::query()->count());
    }

    public function test_a_draft_or_void_invoice_cannot_take_money(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $draft = $this->draftFor($owner, $workspace, $customer);
        $void = app(InvoiceLifecycleService::class)->void($this->invoiceOf($owner, $workspace, $customer, '50.00'), 'Mistake', $owner);
        Sanctum::actingAs($owner);

        foreach ([$draft, $void] as $invoice) {
            $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '10.00', ['allocations' => $this->allocation($invoice, '10.00')]))
                ->assertStatus(409);
        }

        $this->assertSame(0, Receipt::query()->count());
    }

    public function test_an_invoice_of_another_customer_or_workspace_is_rejected(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $other = $this->subscriber($workspace);
        $theirs = $this->invoiceOf($owner, $workspace, $other, '40.00');
        [$otherOwner, $otherWorkspace] = $this->owningWorkspace();
        $foreign = $this->invoiceOf($otherOwner, $otherWorkspace, $this->subscriber($otherWorkspace), '40.00');
        Sanctum::actingAs($owner);

        foreach ([$theirs, $foreign] as $invoice) {
            $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '10.00', ['allocations' => $this->allocation($invoice, '10.00')]))
                ->assertUnprocessable();
        }

        $this->postJson(self::RECEIPTS, $this->receiptPayload($other, '10.00', ['allocations' => [['invoice_id' => fake()->uuid(), 'amount' => '10.00']]]))
            ->assertUnprocessable();
    }

    public function test_a_receipt_without_an_invoice_keeps_its_surplus_and_can_be_allocated_later(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '80.00');
        Sanctum::actingAs($owner);

        $id = $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '100.00'))
            ->assertCreated()->assertJsonPath('data.unallocated_amount', '100.00')->json('data.id');

        $this->postJson(self::RECEIPTS."/{$id}/allocations", ['allocations' => $this->allocation($invoice, '80.00')])
            ->assertOk()->assertJsonPath('data.unallocated_amount', '20.00');

        $this->assertSame('paid', $invoice->fresh()->payment_status->value);
        $this->postJson(self::RECEIPTS."/{$id}/allocations", ['allocations' => $this->allocation($invoice, '30.00')])->assertUnprocessable();
    }

    public function test_a_later_allocation_to_an_invoice_the_receipt_already_pays_adds_to_it(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '50.00');
        Sanctum::actingAs($owner);

        $id = $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '100.00', ['allocations' => $this->allocation($invoice, '30.00')]))
            ->assertCreated()->json('data.id');
        $this->assertSame('partial', $invoice->fresh()->payment_status->value);

        $this->postJson(self::RECEIPTS."/{$id}/allocations", ['allocations' => $this->allocation($invoice, '20.00')])
            ->assertOk()->assertJsonPath('data.unallocated_amount', '50.00');

        $this->assertSame('paid', $invoice->fresh()->payment_status->value);

        $rows = ReceiptAllocation::query()->where('receipt_id', $id)->pluck('amount');
        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(50.0, (float) $rows->first(), 0.001);
    }

    public function test_voiding_a_receipt_puts_the_invoices_back(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $first = $this->invoiceOf($owner, $workspace, $customer, '60.00');
        $second = $this->invoiceOf($owner, $workspace, $customer, '60.00');
        Sanctum::actingAs($owner);

        $id = $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '90.00', [
            'allocations' => [['invoice_id' => $first->id, 'amount' => '60.00'], ['invoice_id' => $second->id, 'amount' => '30.00']],
        ]))->json('data.id');
        $this->twoFactor($owner);

        $this->postJson(self::RECEIPTS."/{$id}/void", ['reason' => 'Entered twice'])->assertOk()->assertJsonPath('data.voided_at', fn ($v) => $v !== null);

        $this->assertSame('unpaid', $first->fresh()->payment_status->value);
        $this->assertSame(InvoiceStatus::Pending, $first->fresh()->status);
        $this->assertNull($first->fresh()->paid_at);
        $this->assertSame('unpaid', $second->fresh()->payment_status->value);
        $this->assertEquals(0, $second->fresh()->amount_paid);
        $this->assertSame('Entered twice', AuditLog::query()->where('action', 'finance.receipt.voided')->value('reason'));

        $this->postJson(self::RECEIPTS."/{$id}/void", ['reason' => 'Again'])->assertStatus(409);
        $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '60.00', ['allocations' => $this->allocation($first, '60.00')]))->assertCreated();
    }

    public function test_the_list_sorts_by_collection_date_or_receipt_date(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $early = Receipt::factory()->create(['workspace_id' => $workspace->id, 'customer_id' => $customer->id, 'number' => 'A-1', 'received_at' => '2026-01-01 10:00:00', 'collected_at' => '2026-03-01 10:00:00']);
        $late = Receipt::factory()->create(['workspace_id' => $workspace->id, 'customer_id' => $customer->id, 'number' => 'A-2', 'received_at' => '2026-02-01 10:00:00', 'collected_at' => '2026-02-15 10:00:00']);
        Receipt::factory()->create(['number' => 'B-1']);
        Sanctum::actingAs($owner);

        $byReceived = $this->getJson(self::RECEIPTS.'?sort=received_at&direction=asc')->assertOk();
        $this->assertSame([$early->id, $late->id], array_column($byReceived->json('data.items'), 'id'));

        $byCollected = $this->getJson(self::RECEIPTS.'?sort=collected_at&direction=asc')->assertOk();
        $this->assertSame([$late->id, $early->id], array_column($byCollected->json('data.items'), 'id'));

        $this->assertSame([$late->id], array_column($this->getJson(self::RECEIPTS.'?sort=collected_at&from=2026-02-01&to=2026-02-28')->json('data.items'), 'id'));
    }

    public function test_permissions_flag_and_two_factor_gate_every_endpoint(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $receipt = Receipt::factory()->create(['workspace_id' => $workspace->id, 'customer_id' => $customer->id]);

        Sanctum::actingAs($this->cashierFor($workspace, []));
        $this->getJson(self::RECEIPTS)->assertForbidden();
        $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '5.00'))->assertForbidden();

        $collector = $this->cashierFor($workspace, [FinancePermission::Collect->value]);
        Sanctum::actingAs($collector);
        $this->getJson(self::RECEIPTS)->assertOk();
        $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '5.00'))->assertCreated();
        $this->postJson(self::RECEIPTS."/{$receipt->id}/void", ['reason' => 'No access'])->assertForbidden();

        Sanctum::actingAs($owner);
        $this->postJson(self::RECEIPTS."/{$receipt->id}/void", ['reason' => 'No 2FA yet'])->assertForbidden();

        [$otherOwner] = $this->owningWorkspace(false);
        Sanctum::actingAs($otherOwner);
        $this->getJson(self::RECEIPTS)->assertNotFound();
    }

    public function test_another_workspaces_receipt_is_not_found(): void
    {
        [$owner] = $this->owningWorkspace();
        $foreign = Receipt::factory()->create();
        Sanctum::actingAs($owner);

        $this->getJson(self::RECEIPTS."/{$foreign->id}")->assertNotFound();
        $this->putJson(self::RECEIPTS."/{$foreign->id}/cheque-status", ['status' => 'deposited'])->assertNotFound();
    }

    public function test_the_proof_is_stored_privately_and_only_a_signed_url_serves_it(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        Sanctum::actingAs($owner);

        $response = $this->post(self::RECEIPTS, $this->receiptPayload($customer, '25.00') + [
            'receipt' => UploadedFile::fake()->image('proof.png'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $receipt = Receipt::query()->firstOrFail();
        $this->assertSame('local', $receipt->receipt_disk);
        Storage::disk('local')->assertExists($receipt->receipt_path);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $url = $response->json('data.receipt_url');
        $this->assertStringContainsString('signature=', $url);
        $this->get('/api/finance/receipts/'.$receipt->id.'/file')->assertForbidden();
        $this->get($url)->assertOk();
    }

    public function test_the_service_creates_receipts_with_gap_free_numbers(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $service = app(ReceiptService::class);

        $one = $service->create($workspace, $this->receiptPayload($customer, '1.00'), $owner);
        $two = $service->create($workspace, $this->receiptPayload($customer, '1.00'), $owner);

        $this->assertStringEndsWith('000001', $one->number);
        $this->assertStringEndsWith('000002', $two->number);
    }

    private function twoFactor(\App\Models\User $user): void
    {
        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();
        app(TwoFactorService::class)->markVerified($user);
    }
}
