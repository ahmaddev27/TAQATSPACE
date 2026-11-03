<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\DocStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InvoiceLifecycleException;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Laravel\Sanctum\Sanctum;

class InvoiceImmutabilityTest extends InvoiceV2TestCase
{
    public function test_money_fields_of_an_issued_invoice_cannot_change(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->issuedFor($owner, $workspace);

        foreach (['amount', 'subtotal', 'tax_amount', 'total'] as $field) {
            try {
                $invoice->forceFill([$field => 999])->save();
                $this->fail("Expected {$field} to be frozen.");
            } catch (InvoiceLifecycleException $e) {
                $this->assertSame(409, $e->render()->getStatusCode());
                $invoice = $invoice->fresh();
            }
        }

        $this->expectException(InvoiceLifecycleException::class);
        $invoice->forceFill(['invoice_number' => 'HACKED-1'])->save();
    }

    public function test_lines_of_an_issued_invoice_cannot_be_edited_or_deleted(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->issuedFor($owner, $workspace);
        $line = $invoice->lines()->firstOrFail();

        try {
            $line->update(['qty' => 100]);
            $this->fail('Expected the line to be frozen.');
        } catch (InvoiceLifecycleException) {
            $this->assertSame(2, $invoice->lines()->count());
        }

        try {
            $line->delete();
            $this->fail('Expected the line to be frozen.');
        } catch (InvoiceLifecycleException) {
            $this->assertSame(2, $invoice->lines()->count());
        }

        $this->expectException(InvoiceLifecycleException::class);
        InvoiceLine::query()->create(['invoice_id' => $invoice->id, 'description' => 'Sneaky', 'qty' => 1, 'unit_price' => 1, 'amount' => 1]);
    }

    public function test_an_issued_invoice_cannot_be_deleted_while_finance_v2_is_on(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->issuedFor($owner, $workspace);

        try {
            $invoice->delete();
            $this->fail('Expected the delete to be refused.');
        } catch (InvoiceLifecycleException) {
            $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        }

        $draft = $this->draftFor($owner, $workspace);
        $draft->delete();
        $this->assertDatabaseMissing('invoices', ['id' => $draft->id]);
    }

    public function test_editing_an_issued_invoice_through_the_api_answers_409(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->issuedFor($owner, $workspace, $customer);
        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/invoices/{$invoice->id}/draft", $this->draftPayload($customer))->assertStatus(409);
        $this->assertSame(2, $invoice->lines()->count());
        $this->assertEquals(33.00, (float) $invoice->fresh()->total);
    }

    public function test_payment_fields_stay_writable_after_issue(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->issuedFor($owner, $workspace);

        $invoice->forceFill(['amount_paid' => 10, 'status' => InvoiceStatus::PartiallyPaid])->save();

        $this->assertSame(PaymentStatus::Partial, $invoice->fresh()->payment_status);
    }

    public function test_a_voided_invoice_is_final(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->issuedFor($owner, $workspace);
        $invoice->forceFill(['doc_status' => DocStatus::Void])->save();

        $this->assertSame(InvoiceStatus::Cancelled, $invoice->fresh()->status);

        $this->expectException(InvoiceLifecycleException::class);
        $invoice->forceFill(['doc_status' => DocStatus::Issued])->save();
    }

    public function test_legacy_invoices_without_the_flag_can_still_be_deleted(): void
    {
        [, $workspace] = $this->owningWorkspace(financeV2: false);
        $customer = $this->subscriber($workspace);
        $invoice = Invoice::factory()->create(['subscription_id' => $customer->subscriptions()->value('id')]);

        $this->assertSame(DocStatus::Issued, $invoice->doc_status);
        $invoice->delete();
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }
}
