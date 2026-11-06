<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\AuditLog;
use App\Models\WalletEntry;
use Laravel\Sanctum\Sanctum;

class WalletForfeitStepUpTest extends WalletTestCase
{
    public function test_forfeiting_needs_a_recent_two_factor_check(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '100.00', $owner);
        Sanctum::actingAs($owner);

        $this->postJson($this->walletUrl($customer, 'forfeits'), ['amount' => '10.00', 'reason' => 'Damage'])->assertForbidden();

        $this->assertSame('100.00', $this->balanceOf($workspace, $customer));
    }

    public function test_forfeiting_needs_a_reason(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '100.00', $owner);
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);

        $this->postJson($this->walletUrl($customer, 'forfeits'), ['amount' => '10.00'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($this->walletUrl($customer, 'forfeits'), ['amount' => '10.00', 'reason' => ''])->assertUnprocessable();
        $this->postJson($this->walletUrl($customer, 'forfeits'), ['amount' => '10.00', 'reason' => 'ab'])->assertUnprocessable();

        $this->assertSame('100.00', $this->balanceOf($workspace, $customer));
    }

    public function test_a_forfeit_with_a_fresh_check_and_a_reason_is_recorded_and_logged(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '100.00', $owner);
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);

        $this->postJson($this->walletUrl($customer, 'forfeits'), ['amount' => '40.00', 'reason' => 'Damaged desk'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'forfeit')
            ->assertJsonPath('data.amount', '-40.00');

        $this->assertSame('60.00', $this->balanceOf($workspace, $customer));
        $log = AuditLog::query()->where('action', 'finance.wallet.forfeited')->firstOrFail();
        $this->assertSame('Damaged desk', $log->reason);
        $this->assertSame($workspace->id, $log->workspace_id);
        $this->assertSame($owner->id, $log->actor_id);
    }

    public function test_forfeiting_more_than_the_balance_is_a_422(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '20.00', $owner);
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);

        $this->postJson($this->walletUrl($customer, 'forfeits'), ['amount' => '20.01', 'reason' => 'Too much'])->assertUnprocessable();

        $this->assertSame(1, WalletEntry::query()->count());
    }
}
