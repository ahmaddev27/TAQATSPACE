<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Services\Finance\DocumentNumberService;
use App\Services\Finance\InvoiceLifecycleService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * SQLite cannot interleave two connections, so these tests check the properties
 * that make the lock-based allocation safe: numbers advance by one under the
 * sequence row, and a rolled-back issue hands its number back.
 */
class DocumentSequenceConcurrencyTest extends InvoiceV2TestCase
{
    public function test_many_issues_produce_unique_consecutive_numbers_without_gaps(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $lifecycle = app(InvoiceLifecycleService::class);

        $drafts = collect(range(1, 6))->map(fn (): Invoice => $this->draftFor($owner, $workspace, $customer));
        $numbers = $drafts->map(fn (Invoice $draft): string => $lifecycle->issue($draft, $owner)->invoice_number);

        $this->assertCount(6, $numbers->unique());
        $this->assertSame(
            range(1, 6),
            $numbers->map(fn (string $number): int => (int) substr($number, -6))->all(),
        );
        $this->assertSame(7, DocumentSequence::query()->where('workspace_id', $workspace->id)->value('next_value'));
    }

    public function test_a_rolled_back_issue_gives_its_number_back(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $numbers = app(DocumentNumberService::class);

        try {
            DB::transaction(function () use ($numbers, $workspace): void {
                $numbers->next($workspace);
                throw new RuntimeException('issue failed after numbering');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertStringEndsWith('000001', $numbers->next($workspace));
        $this->assertStringEndsWith('000002', $numbers->next($workspace));
    }

    public function test_an_existing_sequence_row_continues_from_its_counter(): void
    {
        [, $workspace] = $this->owningWorkspace();
        DocumentSequence::query()->create([
            'workspace_id' => $workspace->id,
            'type' => 'invoice',
            'prefix' => 'ACME-',
            'next_value' => 41,
        ]);

        $this->assertSame('ACME-000041', app(DocumentNumberService::class)->next($workspace));
        $this->assertSame(1, DocumentSequence::query()->count());
    }

    public function test_a_failed_issue_leaves_the_draft_unnumbered_and_the_counter_untouched(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $charge = $this->openCharge($workspace, $customer);
        $draft = app(InvoiceLifecycleService::class)->createDraft($workspace, $this->draftPayload($customer, [
            ['unbilled_charge_id' => $charge->id],
        ]), $owner);

        // The charge gets voided between drafting and issuing.
        $charge->forceFill(['status' => 'voided'])->save();

        try {
            app(InvoiceLifecycleService::class)->issue($draft, $owner);
            $this->fail('Expected the issue to fail.');
        } catch (\App\Exceptions\UnbilledChargeException) {
            $this->assertNull($draft->fresh()->invoice_number);
            $this->assertSame('draft', $draft->fresh()->doc_status->value);
        }

        $this->assertStringEndsWith('000001', app(DocumentNumberService::class)->next($workspace));
    }
}
