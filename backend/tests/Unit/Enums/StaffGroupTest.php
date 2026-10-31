<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\PosPermission;
use App\Enums\StaffGroup;
use PHPUnit\Framework\TestCase;

class StaffGroupTest extends TestCase
{
    public function test_group_values_are_the_three_predefined_groups(): void
    {
        $this->assertSame(['cashier', 'hospitality', 'finance_supervisor'], StaffGroup::values());
    }

    public function test_every_group_maps_to_known_non_empty_permissions(): void
    {
        foreach (StaffGroup::cases() as $group) {
            $this->assertNotEmpty($group->permissions());
            $this->assertSame([], array_diff($group->permissions(), PosPermission::values()));
        }
    }

    public function test_the_cashier_group_matches_the_legacy_default_grant(): void
    {
        $this->assertSame(PosPermission::defaultsForCashier(), StaffGroup::Cashier->permissions());
    }

    public function test_groups_grant_distinct_permission_sets(): void
    {
        $this->assertSame(
            [PosPermission::Sell->value, PosPermission::ManageProducts->value],
            StaffGroup::Hospitality->permissions(),
        );
        $this->assertNotContains(PosPermission::Refund->value, StaffGroup::Hospitality->permissions());
        $this->assertNotContains(PosPermission::Sell->value, StaffGroup::FinanceSupervisor->permissions());
    }
}
