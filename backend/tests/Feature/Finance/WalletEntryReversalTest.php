<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\WalletEntryType;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WalletEntry;
use App\Services\Finance\WalletLedger;
use Laravel\Sanctum\Sanctum;

/** A deposit or forfeit recorded by mistake is undone with the opposite entry; the original stays. */
class WalletEntryReversalTest extends WalletTestCase
{
    private function reverseUrl(User $customer, WalletEntry $entry): string
    {
        return $this->walletUrl($customer, "entries/{$entry->id}/reverse");
    }

    public function test_a_wrong_deposit_is_reversed_with_a_debit_a_reason_and_an_audit_entry(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '20.00', $owner);
        $wrong = $this->deposit($workspace, $customer, '50.00', $owner);
        $this->actAsOwner($owner);

        $response = $this->postJson($this->reverseUrl($customer, $wrong), ['reason' => 'Recorded on the wrong customer'])->assertCreated();

        $response->assertJsonPath('data.type', 'debit')->assertJsonPath('data.amount', '-50.00')
            ->assertJsonPath('data.related_type', WalletLedger::RELATED_ENTRY)->assertJsonPath('data.related_id', $wrong->id)
            ->assertJsonPath('data.reversible', false);
        $this->assertSame('20.00', $this->balanceOf($workspace, $customer));
        $this->assertNotNull($wrong->fresh(), 'the original stays on the ledger');
        $log = AuditLog::query()->where('action', 'finance.wallet.entry_reversed')->firstOrFail();
        $this->assertSame('Recorded on the wrong customer', $log->reason);
        $this->assertSame($wrong->id, $log->changes['reversed_entry_id']);

        $items = collect($this->getJson($this->walletUrl($customer))->assertOk()->json('data.items'))->keyBy('id');
        $this->assertTrue($items[$wrong->id]['reversed']);
        $this->assertFalse($items[$wrong->id]['reversible']);
    }

    public function test_a_wrong_forfeit_is_reversed_with_a_credit(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '100.00', $owner);
        $forfeit = $this->service()->forfeit($workspace, $customer->id, '15.00', 'Damage', null, $owner);
        $this->actAsOwner($owner);

        $this->postJson($this->reverseUrl($customer, $forfeit), ['reason' => 'The desk was fine'])->assertCreated()->assertJsonPath('data.type', 'credit');

        $this->assertSame('100.00', $this->balanceOf($workspace, $customer));
    }

    public function test_a_deposit_already_spent_cannot_be_reversed(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $deposit = $this->deposit($workspace, $customer, '50.00', $owner);
        $this->giveBack($workspace, $customer, '30.00', $owner);
        $this->actAsOwner($owner);

        $this->postJson($this->reverseUrl($customer, $deposit), ['reason' => 'Wrong amount'])->assertUnprocessable();

        $this->assertSame('20.00', $this->balanceOf($workspace, $customer));
    }

    public function test_an_entry_is_reversed_once_and_a_repeated_key_answers_with_the_first_reversal(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $deposit = $this->deposit($workspace, $customer, '50.00', $owner);
        $this->actAsOwner($owner);
        $payload = ['reason' => 'Typed twice', 'request_key' => 'reverse-1'];

        $first = $this->postJson($this->reverseUrl($customer, $deposit), $payload)->assertCreated();
        $again = $this->postJson($this->reverseUrl($customer, $deposit), $payload)->assertCreated();
        $this->postJson($this->reverseUrl($customer, $deposit), ['reason' => 'Typed twice'])->assertStatus(409)->assertJsonPath('code', 'wallet_entry_already_reversed');

        $this->assertSame($first->json('data.id'), $again->json('data.id'));
        $this->assertSame('0.00', $this->balanceOf($workspace, $customer));
        $this->assertSame(1, WalletEntry::query()->where('type', WalletEntryType::Debit)->count());
    }

    public function test_only_a_deposit_or_a_forfeit_can_be_reversed(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->deposit($workspace, $customer, '100.00', $owner);
        $this->service()->settleAgainstInvoice($workspace, $customer->id, $invoice->id, '40.00', null, $owner);
        $settlement = WalletEntry::query()->where('type', WalletEntryType::Settlement)->firstOrFail();
        $this->actAsOwner($owner);

        $this->postJson($this->reverseUrl($customer, $settlement), ['reason' => 'Use the receipt'])->assertUnprocessable();

        $this->assertSame('60.00', $this->balanceOf($workspace, $customer));
    }

    public function test_reversing_needs_a_recent_two_factor_check_a_reason_and_the_issue_void_grant(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $deposit = $this->deposit($workspace, $customer, '50.00', $owner);

        Sanctum::actingAs($owner);
        $this->postJson($this->reverseUrl($customer, $deposit), ['reason' => 'Wrong'])->assertForbidden();

        $this->actAsOwner($owner);
        $this->postJson($this->reverseUrl($customer, $deposit), [])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($this->reverseUrl($customer, $deposit), ['reason' => 'no'])->assertUnprocessable()->assertJsonValidationErrors('reason');

        Sanctum::actingAs($this->cashierFor($workspace, ['finance_collect']));
        $this->postJson($this->reverseUrl($customer, $deposit), ['reason' => 'Wrong'])->assertForbidden();

        $this->assertSame('50.00', $this->balanceOf($workspace, $customer));
    }

    public function test_an_entry_of_another_workspace_or_customer_cannot_be_reversed(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        [$otherOwner, $other] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $neighbour = $this->subscriber($workspace);
        $theirs = $this->subscriber($other);
        $this->deposit($workspace, $neighbour, '10.00', $owner);
        $foreign = $this->deposit($other, $theirs, '50.00', $otherOwner);
        $mine = $this->deposit($workspace, $customer, '50.00', $owner);
        $this->actAsOwner($owner);

        $this->postJson($this->reverseUrl($theirs, $foreign), ['reason' => 'Foreign'])->assertUnprocessable();
        $this->postJson($this->reverseUrl($neighbour, $mine), ['reason' => 'Wrong wallet'])->assertNotFound();

        $this->assertSame('50.00', $this->balanceOf($other, $theirs));
        $this->assertSame('50.00', $this->balanceOf($workspace, $customer));
    }
}
