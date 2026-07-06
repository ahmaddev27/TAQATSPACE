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
use App\Services\Pos\CashierManagementService;
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
            'declined_at' => null,
        ]);
        Notification::assertSentOnDemand(CashierInvitationNotification::class);
    }

    public function test_invite_rejects_an_already_onboarded_email_but_allows_a_fresh_one(): void
    {
        Notification::fake();
        [$owner] = $this->owningWorkspace();
        User::factory()->create([
            'email' => 'onboarded@mail.ps',
            'onboarding_completed_at' => now(),
        ]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/cashiers/invite', ['email' => 'onboarded@mail.ps'])
            ->assertStatus(422);

        // A brand-new email (no account) is allowed.
        $this->postJson('/api/workspace/cashiers/invite', ['email' => 'fresh@mail.ps'])
            ->assertCreated();

        // A not-yet-onboarded SSO account is also allowed.
        User::factory()->create([
            'email' => 'pending@mail.ps',
            'onboarding_completed_at' => null,
            'status' => UserStatus::PendingVerification,
        ]);
        $this->postJson('/api/workspace/cashiers/invite', ['email' => 'pending@mail.ps'])
            ->assertCreated();
    }

    public function test_pending_for_email_finds_an_open_invitation(): void
    {
        [, $workspace] = $this->owningWorkspace();
        CashierInvitation::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'barista@cafe.ps',
        ]);

        $found = app(CashierManagementService::class)->pendingForEmail('Barista@Cafe.ps');

        $this->assertNotNull($found);
        $this->assertSame('barista@cafe.ps', $found->email);
    }

    public function test_onboarding_user_accepts_and_becomes_a_scoped_cashier(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $invitation = CashierInvitation::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'barista@cafe.ps',
            'permissions' => [PosPermission::Sell->value],
        ]);
        $user = $this->onboardingUser('barista@cafe.ps');
        Sanctum::actingAs($user);

        $this->postJson("/api/cashier/invitations/{$invitation->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.role', UserRole::Cashier->value)
            ->assertJsonPath('data.user.needs_onboarding', false);

        $cashier = $user->fresh();
        $this->assertSame($workspace->id, $cashier->workspace_id);
        $this->assertTrue($cashier->isCashier());
        $this->assertNotNull($cashier->onboarding_completed_at);
        $this->assertTrue($cashier->can(PosPermission::Sell->value));
        $this->assertFalse($cashier->can(PosPermission::Refund->value));
        $this->assertDatabaseHas('cashier_invitations', [
            'id' => $invitation->id,
            'accepted_user_id' => $cashier->id,
        ]);
    }

    public function test_accept_is_rejected_on_email_mismatch(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $invitation = CashierInvitation::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'barista@cafe.ps',
        ]);
        $user = $this->onboardingUser('someone-else@mail.ps');
        Sanctum::actingAs($user);

        $this->postJson("/api/cashier/invitations/{$invitation->id}/accept")
            ->assertStatus(422);

        $this->assertNull($user->fresh()->workspace_id);
    }

    public function test_accept_is_rejected_when_user_already_onboarded(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $invitation = CashierInvitation::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'barista@cafe.ps',
        ]);
        $user = User::factory()->create([
            'email' => 'barista@cafe.ps',
            'onboarding_completed_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/cashier/invitations/{$invitation->id}/accept")
            ->assertStatus(422);
    }

    public function test_decline_marks_the_invitation_and_it_stops_being_pending(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $invitation = CashierInvitation::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'barista@cafe.ps',
        ]);
        $user = $this->onboardingUser('barista@cafe.ps');
        Sanctum::actingAs($user);

        $this->postJson("/api/cashier/invitations/{$invitation->id}/decline")->assertOk();

        $this->assertNotNull($invitation->fresh()->declined_at);
        $this->assertNull(
            app(CashierManagementService::class)->pendingForEmail('barista@cafe.ps')
        );
    }

    public function test_me_exposes_the_pending_cashier_invitation_for_a_matching_onboarding_user(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $invitation = CashierInvitation::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'barista@cafe.ps',
            'permissions' => [PosPermission::Sell->value],
        ]);
        $user = $this->onboardingUser('barista@cafe.ps');
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.pending_cashier_invitation.id', $invitation->id)
            ->assertJsonPath('data.user.pending_cashier_invitation.workspace_name', $workspace->name)
            ->assertJsonPath('data.user.pending_cashier_invitation.permissions', [PosPermission::Sell->value]);
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

    public function test_inviting_an_email_that_already_has_an_open_invite_is_rejected(): void
    {
        Notification::fake();
        [$owner, $workspace] = $this->owningWorkspace();
        CashierInvitation::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'dup@mail.ps',
        ]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/cashiers/invite', ['email' => 'dup@mail.ps'])
            ->assertStatus(422);
    }

    public function test_owner_resends_and_deletes_an_invitation(): void
    {
        Notification::fake();
        [$owner, $workspace] = $this->owningWorkspace();
        $invitation = CashierInvitation::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'staff@mail.ps',
            'expires_at' => now()->addDay(),
        ]);
        Sanctum::actingAs($owner);

        // Resend re-sends the email + pushes the expiry out.
        $this->postJson("/api/workspace/cashiers/invitations/{$invitation->id}/resend")->assertOk();
        Notification::assertSentOnDemand(CashierInvitationNotification::class);
        $this->assertTrue($invitation->fresh()->expires_at->gt(now()->addDays(5)));

        // A foreign workspace's invite is not touchable here.
        $foreign = CashierInvitation::factory()->create();
        $this->postJson("/api/workspace/cashiers/invitations/{$foreign->id}/resend")->assertNotFound();

        // Delete revokes it.
        $this->deleteJson("/api/workspace/cashiers/invitations/{$invitation->id}")->assertOk();
        $this->assertDatabaseMissing('cashier_invitations', ['id' => $invitation->id]);
    }

    public function test_owner_permanently_deletes_a_cashier(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $cashier = User::factory()->create([
            'role' => UserRole::Cashier->value,
            'workspace_id' => $workspace->id,
        ]);
        $foreign = User::factory()->create(['role' => UserRole::Cashier->value]);
        Sanctum::actingAs($owner);

        // A cashier of another workspace is not deletable here.
        $this->deleteJson("/api/workspace/cashiers/{$foreign->id}/permanent")->assertNotFound();

        $this->deleteJson("/api/workspace/cashiers/{$cashier->id}/permanent")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $cashier->id]);
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

    /** A fresh SSO account that still needs onboarding. */
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
