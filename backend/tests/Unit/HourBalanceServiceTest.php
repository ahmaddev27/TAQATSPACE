<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\HourLedgerReason;
use App\Models\HourLedgerEntry;
use App\Models\User;
use App\Services\Hours\HourBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HourBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_is_the_signed_sum_of_the_users_ledger_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $entries = [
            [$user, 480, HourLedgerReason::Credit],
            [$user, -90, HourLedgerReason::Usage],
            [$user, 30, HourLedgerReason::Adjustment],
            [$other, 600, HourLedgerReason::Credit],
        ];
        foreach ($entries as [$owner, $minutes, $reason]) {
            HourLedgerEntry::query()->create(['user_id' => $owner->id, 'minutes' => $minutes, 'reason' => $reason->value]);
        }

        $this->assertSame(420, app(HourBalanceService::class)->balance($user));
        $this->assertSame(0, app(HourBalanceService::class)->balance(User::factory()->create()));
    }
}
