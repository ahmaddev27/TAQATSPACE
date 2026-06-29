<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\City;
use App\Models\User;
use App\Notifications\NewWorkspaceRegistrationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAppRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAppRoles();
    }

    /** A freshly-provisioned SSO account awaiting role selection. */
    private function ssoUser(): User
    {
        $user = User::factory()->create([
            'role' => UserRole::Freelancer,
            'status' => UserStatus::PendingVerification,
            'onboarding_completed_at' => null,
            'sso_sub' => 'sub-'.uniqid(),
            'specialty' => null,
        ]);
        $user->syncRoles([UserRole::Freelancer->value]);

        return $user;
    }

    private function actingAsToken(User $user)
    {
        return $this->withToken($user->createToken('sso')->plainTextToken);
    }

    public function test_freelancer_onboarding_activates_account(): void
    {
        Notification::fake();
        $user = $this->ssoUser();

        $this->actingAsToken($user)->postJson('/api/auth/complete-onboarding', [
            'role' => UserRole::Freelancer->value,
            'specialty' => 'Writer',
        ])->assertOk()
            ->assertJsonPath('data.role', UserRole::Freelancer->value)
            ->assertJsonPath('data.next', 'dashboard');

        $user->refresh();
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNotNull($user->onboarding_completed_at);
        $this->assertSame('Writer', $user->specialty);

        Notification::assertNothingSent();
    }

    public function test_owner_onboarding_stays_pending_creates_workspace_and_notifies(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $city = City::create(['name_ar' => 'الرياض', 'name_en' => 'Riyadh', 'is_active' => true]);
        $user = $this->ssoUser();

        $this->actingAsToken($user)->postJson('/api/auth/complete-onboarding', [
            'role' => UserRole::WorkspaceOwner->value,
            'workspace_name' => 'SSO Space',
            'city_id' => $city->id,
            'address' => '99 Owner Ave',
        ])->assertOk()
            ->assertJsonPath('data.role', UserRole::WorkspaceOwner->value)
            ->assertJsonPath('data.next', 'pending');

        $user->refresh();
        // Owners await admin approval.
        $this->assertSame(UserStatus::PendingVerification, $user->status);
        $this->assertTrue($user->hasRole(UserRole::WorkspaceOwner->value));
        $this->assertDatabaseHas('workspaces', [
            'owner_id' => $user->id,
            'name' => 'SSO Space',
        ]);

        Notification::assertSentTo($admin, NewWorkspaceRegistrationNotification::class);
    }

    public function test_already_onboarded_user_cannot_re_onboard(): void
    {
        $user = User::factory()->freelancer()->create([
            'onboarding_completed_at' => now(),
        ]);

        // CompleteOnboardingRequest authorize() rejects classified accounts.
        $this->actingAsToken($user)->postJson('/api/auth/complete-onboarding', [
            'role' => UserRole::Freelancer->value,
            'specialty' => 'Writer',
        ])->assertStatus(403);
    }

    public function test_freelancer_onboarding_requires_specialty(): void
    {
        $user = $this->ssoUser();

        $this->actingAsToken($user)->postJson('/api/auth/complete-onboarding', [
            'role' => UserRole::Freelancer->value,
        ])->assertStatus(422)->assertJsonValidationErrors(['specialty']);
    }

    public function test_onboarding_requires_authentication(): void
    {
        $this->postJson('/api/auth/complete-onboarding', [
            'role' => UserRole::Freelancer->value,
            'specialty' => 'Writer',
        ])->assertStatus(401);
    }
}
