<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\AuditLog;
use App\Models\CreditNote;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A credit-note form sends one request key per opening: a double click or a
 * retry of that request returns the first note instead of a second credit,
 * while two equal credits issued on purpose are both kept.
 */
class CreditNoteRequestKeyTest extends ReceiptTestCase
{
    public function test_the_same_request_key_answers_with_the_first_note(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        $this->actAsOwner($owner);
        $payload = ['amount' => '10.00', 'reason' => 'Discount', 'request_key' => 'form-1'];

        $first = $this->postJson($this->url($invoice), $payload)->assertCreated();
        $second = $this->postJson($this->url($invoice), $payload)->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame($first->json('data.number'), $second->json('data.number'));
        $this->assertSame(1, CreditNote::query()->count());
        $this->assertEquals(10, $invoice->fresh()->credited_amount);
        $this->assertSame(1, AuditLog::query()->where('action', 'finance.credit_note.issued')->count());
    }

    public function test_the_same_request_key_with_other_data_is_refused(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $other = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->actAsOwner($owner);
        $this->postJson($this->url($invoice), ['amount' => '10.00', 'reason' => 'Discount', 'request_key' => 'form-1'])->assertCreated();

        foreach ([
            [$invoice, ['amount' => '11.00', 'reason' => 'Discount']],
            [$invoice, ['amount' => '10.00', 'reason' => 'Another reason']],
            [$other, ['amount' => '10.00', 'reason' => 'Discount']],
        ] as [$target, $body]) {
            $this->postJson($this->url($target), [...$body, 'request_key' => 'form-1'])
                ->assertStatus(409)
                ->assertJsonPath('message', __('messages.credit_note_request_key_reused'));
        }

        $this->assertSame(1, CreditNote::query()->count());
        $this->assertEquals(0, $other->fresh()->credited_amount);
    }

    public function test_a_key_that_races_onto_another_invoice_is_refused_by_the_unique_index(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $first = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $second = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->actAsOwner($owner);
        $raced = false;
        // The two invoices lock separately, so a request on the first can commit its note between
        // this request's key lookup and its insert; only the unique index sees that.
        CreditNote::creating(static function (CreditNote $note) use (&$raced, $first): void {
            if ($raced) {
                return;
            }
            $raced = true;
            DB::table('credit_notes')->insert([
                'id' => (string) Str::uuid(),
                'workspace_id' => $note->workspace_id,
                'invoice_id' => $first->id,
                'number' => 'RACE-1',
                'amount' => '10.00',
                'reason' => 'Discount',
                'request_key' => 'form-1',
                'issued_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->postJson($this->url($second), ['amount' => '10.00', 'reason' => 'Discount', 'request_key' => 'form-1'])
            ->assertStatus(409)
            ->assertJsonPath('message', __('messages.credit_note_request_key_reused'));

        $this->assertTrue($raced);
        $this->assertFalse(CreditNote::query()->where('invoice_id', $second->id)->exists());
        $this->assertEquals(0, $second->fresh()->credited_amount);
    }

    public function test_two_equal_credits_issued_on_purpose_are_both_kept(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        $this->actAsOwner($owner);

        $this->postJson($this->url($invoice), ['amount' => '10.00', 'reason' => 'Discount', 'request_key' => 'form-1'])->assertCreated();
        $this->postJson($this->url($invoice), ['amount' => '10.00', 'reason' => 'Discount', 'request_key' => 'form-2'])->assertCreated();
        $this->postJson($this->url($invoice), ['amount' => '10.00', 'reason' => 'Discount'])->assertCreated();
        $this->postJson($this->url($invoice), ['amount' => '10.00', 'reason' => 'Discount'])->assertCreated();

        $this->assertSame(4, CreditNote::query()->count());
        $this->assertEquals(40, $invoice->fresh()->credited_amount);
    }

    public function test_a_request_key_belongs_to_its_workspace(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        [$otherOwner, $otherWorkspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        $foreign = $this->invoiceOf($otherOwner, $otherWorkspace, $this->subscriber($otherWorkspace), '100.00');

        $this->actAsOwner($owner);
        $this->postJson($this->url($invoice), ['amount' => '10.00', 'reason' => 'Discount', 'request_key' => 'form-1'])->assertCreated();
        $this->actAsOwner($otherOwner);
        $this->postJson($this->url($foreign), ['amount' => '10.00', 'reason' => 'Discount', 'request_key' => 'form-1'])->assertCreated();

        $this->assertSame(2, CreditNote::query()->count());
    }

    public function test_the_request_key_is_validated(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        $this->actAsOwner($owner);

        $this->postJson($this->url($invoice), ['amount' => '10.00', 'reason' => 'Discount', 'request_key' => str_repeat('k', 65)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('request_key');
    }

    private function url(Invoice $invoice): string
    {
        return "/api/workspace/invoices/{$invoice->id}/credit-notes";
    }
}
