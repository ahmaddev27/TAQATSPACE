<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CashierInvitation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Guards the deploy-safety fix: the `cashier` role + POS permissions must exist
 * from the MIGRATIONS alone (deploys run `migrate`, never the seeders). These
 * tests deliberately do NOT seed.
 */
class CashierRoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_role_and_pos_permissions_exist_from_migrations(): void
    {
        $this->assertTrue(Role::where(['name' => 'cashier', 'guard_name' => 'web'])->exists());

        foreach (['pos_sell', 'pos_refund', 'pos_manage_products', 'pos_view_reports'] as $permission) {
            $this->assertTrue(
                Permission::where(['name' => $permission, 'guard_name' => 'web'])->exists(),
                "Missing POS permission: {$permission}",
            );
        }
    }

    public function test_accepting_an_invitation_succeeds_without_seeding(): void
    {
        $workspace = Workspace::factory()->create();

        // A freshly SSO-provisioned account: freelancer placeholder, not onboarded.
        $user = User::factory()->create([
            'email' => 'newstaff@mail.ps',
            'role' => UserRole::Freelancer->value,
            'status' => UserStatus::PendingVerification->value,
            'onboarding_completed_at' => null,
        ]);

        $invitation = CashierInvitation::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'newstaff@mail.ps',
        ]);

        Sanctum::actingAs($user);
        $this->postJson("/api/cashier/invitations/{$invitation->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.role', UserRole::Cashier->value);

        $user->refresh();
        $this->assertTrue($user->isCashier());
        $this->assertSame($workspace->id, $user->workspace_id);
    }
}
