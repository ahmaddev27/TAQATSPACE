<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Enums\PosPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CashierInvitation;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\CashierInvitationNotification;
use Database\Seeders\PosPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashierManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PosPermissionSeeder::class);
    }

    public function test_owner_invites_a_cashier_and_a_branded_email_is_sent(): void
    {
        Notification::fake();
        [$owner] = $this->owningWorkspace();
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/workspace/cashiers/invite', [
            'email' => 'barista@cafe.ps',
            'name' => 'Barista',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('cashier_invitations', [
            'email' => 'barista@cafe.ps',
            'accepted_at' => null,
        ]);
        Notification::assertSentOnDemand(CashierInvitationNotification::class);
    }

    public function test_invitee_accepts_and_receives_a_scoped_cashier_account(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $token = 'raw-token-value-123';
        CashierInvitation::factory()->withToken($token)->create([
            'workspace_id' => $workspace->id,
            'email' => 'barista@cafe.ps',
            'permissions' => [PosPermission::Sell->value],
        ]);

        $response = $this->postJson('/api/cashier/accept', [
            'token' => $token,
            'name' => 'Barista',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertCreated()->assertJsonPath('data.user.role', UserRole::Cashier->value);

        $cashier = User::query()->where('email', 'barista@cafe.ps')->firstOrFail();
        $this->assertSame($workspace->id, $cashier->workspace_id);
        $this->assertTrue($cashier->isCashier());
        $this->assertTrue($cashier->can(PosPermission::Sell->value));
        $this->assertFalse($cashier->can(PosPermission::Refund->value));
        $this->assertDatabaseHas('cashier_invitations', [
            'email' => 'barista@cafe.ps',
            'accepted_user_id' => $cashier->id,
        ]);
    }

    public function test_accepting_an_expired_token_fails(): void
    {
        $token = 'expired-token';
        CashierInvitation::factory()->withToken($token)->expired()->create();

        $this->postJson('/api/cashier/accept', [
            'token' => $token,
            'name' => 'X',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['role' => UserRole::Cashier->value]);
    }

    public function test_cannot_invite_an_email_that_already_belongs_to_a_user(): void
    {
        [$owner] = $this->owningWorkspace();
        User::factory()->create(['email' => 'taken@mail.ps']);
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/cashiers/invite', ['email' => 'taken@mail.ps'])
            ->assertStatus(422);
    }

    public function test_owner_lists_and_deactivates_only_their_own_cashiers(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $cashier = User::factory()->create([
            'role' => UserRole::Cashier->value,
            'workspace_id' => $workspace->id,
        ]);
        $foreign = User::factory()->create(['role' => UserRole::Cashier->value]);
        Sanctum::actingAs($owner);

        $this->getJson('/api/workspace/cashiers')
            ->assertOk()
            ->assertJsonCount(1, 'data.cashiers');

        $this->deleteJson("/api/workspace/cashiers/{$cashier->id}")->assertOk();
        $this->assertSame(UserStatus::Suspended, $cashier->fresh()->status);

        // A cashier of another workspace is not manageable here.
        $this->deleteJson("/api/workspace/cashiers/{$foreign->id}")->assertNotFound();
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
}
