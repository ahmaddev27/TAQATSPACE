<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Enums\FinancePermission;
use App\Enums\PosPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CashierInvitation;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PosPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Invitations stored before the finance permissions existed (POS-only) still work. */
class CashierInvitationLegacyPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PosPermissionSeeder::class);
    }

    public function test_a_stored_pos_only_invitation_is_accepted_with_exactly_those_grants(): void
    {
        $workspace = Workspace::factory()->create();
        $invitation = CashierInvitation::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'old@cafe.ps',
            'permissions' => [PosPermission::Sell->value, PosPermission::ViewReports->value],
        ]);
        $user = User::factory()->create([
            'email' => 'old@cafe.ps',
            'role' => UserRole::Freelancer->value,
            'status' => UserStatus::PendingVerification->value,
            'onboarding_completed_at' => null,
            'workspace_id' => null,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/cashier/invitations/{$invitation->id}/accept")->assertOk();

        $staff = $user->fresh();
        $this->assertEqualsCanonicalizing(
            [PosPermission::Sell->value, PosPermission::ViewReports->value],
            $staff->getPermissionNames()->all(),
        );
        $this->assertFalse($staff->can(FinancePermission::Collect->value));
    }

    public function test_owner_can_grant_finance_permissions_to_existing_staff(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $staff = User::factory()->create(['role' => UserRole::Cashier->value, 'workspace_id' => $workspace->id]);
        $staff->assignRole(UserRole::Cashier->value);
        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/staff/{$staff->id}/permissions", [
            'permissions' => [PosPermission::Sell->value, FinancePermission::Collect->value],
        ])->assertOk();

        $this->assertTrue($staff->fresh()->can(FinancePermission::Collect->value));
    }
}
