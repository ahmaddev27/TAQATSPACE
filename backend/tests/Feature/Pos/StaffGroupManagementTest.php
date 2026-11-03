<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Enums\PosPermission;
use App\Enums\StaffGroup;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\PosProduct;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PosPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Staff groups (cashier / hospitality / finance supervisor): group → permission
 * map on invite + accept, per-user adjustment, and permission-escalation guards.
 */
class StaffGroupManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PosPermissionSeeder::class);
    }

    /** @return array<string, array{0: StaffGroup}> */
    public static function staffGroups(): array
    {
        return [
            'cashier' => [StaffGroup::Cashier],
            'hospitality' => [StaffGroup::Hospitality],
            'finance supervisor' => [StaffGroup::FinanceSupervisor],
        ];
    }

    #[DataProvider('staffGroups')]
    public function test_inviting_with_a_group_grants_only_that_groups_permissions(StaffGroup $group): void
    {
        Notification::fake();
        [$owner, $workspace] = $this->owningWorkspace();
        Sanctum::actingAs($owner);

        $invitationId = $this->postJson('/api/workspace/staff/invite', [
            'email' => 'staff@cafe.ps',
            'staff_group' => $group->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.staff_group', $group->value)
            ->assertJsonPath('data.permissions', $group->permissions())
            ->json('data.id');

        $user = $this->onboardingUser('staff@cafe.ps');
        Sanctum::actingAs($user);
        $this->postJson("/api/cashier/invitations/{$invitationId}/accept")->assertOk();

        $staff = $user->fresh();
        $this->assertSame($workspace->id, $staff->workspace_id);
        $this->assertSame($group, $staff->staff_group);
        $granted = $staff->getPermissionNames()->sort()->values()->all();
        $expected = collect($group->permissions())->sort()->values()->all();
        $this->assertSame($expected, $granted);
    }

    public function test_invite_defaults_to_the_cashier_group_and_rejects_unknown_groups(): void
    {
        Notification::fake();
        [$owner] = $this->owningWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/staff/invite', ['email' => 'a@cafe.ps'])
            ->assertCreated()
            ->assertJsonPath('data.staff_group', StaffGroup::Cashier->value)
            ->assertJsonPath('data.permissions', StaffGroup::Cashier->permissions());

        $this->postJson('/api/workspace/staff/invite', ['email' => 'b@cafe.ps', 'staff_group' => 'root'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('staff_group');
    }

    public function test_explicit_permissions_refine_the_group_on_invite(): void
    {
        Notification::fake();
        [$owner] = $this->owningWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/staff/invite', [
            'email' => 'c@cafe.ps',
            'staff_group' => StaffGroup::Hospitality->value,
            'permissions' => [PosPermission::Sell->value],
        ])
            ->assertCreated()
            ->assertJsonPath('data.permissions', [PosPermission::Sell->value]);
    }

    public function test_owner_adds_and_removes_a_single_permission_with_immediate_effect(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $staff = $this->staffFor($workspace, StaffGroup::Cashier);
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'price' => 5, 'stock_qty' => 10]);

        Sanctum::actingAs($staff);
        $orderId = $this->postJson('/api/pos/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/pos/orders/{$orderId}/pay", ['method' => 'cash'])->assertOk();
        $this->postJson("/api/pos/orders/{$orderId}/refund")->assertStatus(403);

        // Owner grants refund on top of the group.
        Sanctum::actingAs($owner);
        $this->putJson("/api/workspace/staff/{$staff->id}/permissions", [
            'permissions' => [...StaffGroup::Cashier->permissions(), PosPermission::Refund->value],
        ])
            ->assertOk()
            ->assertJsonPath('data.staff_group', StaffGroup::Cashier->value)
            ->assertJsonFragment(['permissions' => [
                PosPermission::Sell->value,
                PosPermission::ViewReports->value,
                PosPermission::Refund->value,
            ]]);

        Sanctum::actingAs($staff->fresh());
        $this->postJson("/api/pos/orders/{$orderId}/refund")->assertOk();

        // Owner removes it again: the effect is immediate.
        Sanctum::actingAs($owner);
        $this->putJson("/api/workspace/staff/{$staff->id}/permissions", [
            'permissions' => StaffGroup::Cashier->permissions(),
        ])->assertOk();

        Sanctum::actingAs($staff->fresh());
        $this->postJson('/api/pos/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertCreated();
        $this->assertFalse($staff->fresh()->can(PosPermission::Refund->value));
    }

    public function test_owner_can_move_staff_to_another_group(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $staff = $this->staffFor($workspace, StaffGroup::Cashier);
        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/staff/{$staff->id}/permissions", [
            'staff_group' => StaffGroup::FinanceSupervisor->value,
            'permissions' => StaffGroup::FinanceSupervisor->permissions(),
        ])->assertOk()->assertJsonPath('data.staff_group', StaffGroup::FinanceSupervisor->value);

        $this->assertSame(StaffGroup::FinanceSupervisor, $staff->fresh()->staff_group);
    }

    public function test_staff_cannot_exceed_their_permissions(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $hospitality = $this->staffFor($workspace, StaffGroup::Hospitality);
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'price' => 5, 'stock_qty' => 10]);
        Sanctum::actingAs($hospitality);

        $orderId = $this->postJson('/api/pos/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/pos/orders/{$orderId}/pay", ['method' => 'cash'])->assertOk();

        // Outside the Hospitality group: refunds and reports.
        $this->postJson("/api/pos/orders/{$orderId}/refund")->assertStatus(403);
        $this->getJson('/api/pos/reports')->assertStatus(403);
    }

    public function test_staff_cannot_grant_themselves_permissions_via_the_owner_endpoints(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $staff = $this->staffFor($workspace, StaffGroup::Hospitality);
        Sanctum::actingAs($staff);

        $this->putJson("/api/workspace/staff/{$staff->id}/permissions", [
            'permissions' => PosPermission::values(),
        ])->assertStatus(403);
        $this->getJson('/api/workspace/staff')->assertStatus(403);
        $this->assertFalse($staff->fresh()->can(PosPermission::Refund->value));
    }

    public function test_unknown_permissions_are_rejected_on_update(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $staff = $this->staffFor($workspace, StaffGroup::Cashier);
        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/staff/{$staff->id}/permissions", ['permissions' => ['manage_everything']])
            ->assertStatus(422);
    }

    public function test_staff_index_lists_groups_and_keeps_the_legacy_alias(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $this->staffFor($workspace, StaffGroup::Hospitality);
        Sanctum::actingAs($owner);

        $this->getJson('/api/workspace/staff')
            ->assertOk()
            ->assertJsonCount(1, 'data.staff')
            ->assertJsonCount(1, 'data.cashiers')
            ->assertJsonPath('data.staff.0.staff_group', StaffGroup::Hospitality->value);
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function owningWorkspace(): array
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        return [$owner, $workspace];
    }

    private function staffFor(Workspace $workspace, StaffGroup $group): User
    {
        $staff = User::factory()->create([
            'role' => UserRole::Cashier->value,
            'workspace_id' => $workspace->id,
            'staff_group' => $group->value,
        ]);
        $staff->assignRole(UserRole::Cashier->value);
        $staff->syncPermissions($group->permissions());

        return $staff->fresh();
    }

    private function onboardingUser(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'role' => UserRole::Freelancer->value,
            'status' => UserStatus::PendingVerification->value,
            'onboarding_completed_at' => null,
            'workspace_id' => null,
        ]);
    }
}
