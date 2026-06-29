<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\City;
use App\Models\User;
use App\Notifications\NewWorkspaceRegistrationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAppRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAppRoles();
    }

    public function test_freelancer_registration_creates_pending_user_with_token(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Freelancer One',
            'email' => 'freel@mail.ps',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UserRole::Freelancer->value,
            'specialty' => 'Designer',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', UserRole::Freelancer->value)
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertNotEmpty($response->json('data.token'));

        $user = User::where('email', 'freel@mail.ps')->firstOrFail();
        $this->assertSame(UserRole::Freelancer, $user->role);
        // Self-registration starts pending verification and onboarding is done.
        $this->assertSame(UserStatus::PendingVerification, $user->status);
        $this->assertNotNull($user->onboarding_completed_at);
        $this->assertTrue($user->hasRole(UserRole::Freelancer->value));

        // No admin notification for freelancer registration.
        Notification::assertNothingSent();
    }

    public function test_owner_registration_creates_workspace_and_notifies_admins(): void
    {
        Notification::fake();
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $city = City::create(['name_ar' => 'الرياض', 'name_en' => 'Riyadh', 'is_active' => true]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Owner One',
            'email' => 'owner@mail.ps',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UserRole::WorkspaceOwner->value,
            'workspace_name' => 'My Space',
            'city_id' => $city->id,
            'address' => '123 Main St',
            'license_file' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
            'id_document' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', UserRole::WorkspaceOwner->value);

        $owner = User::where('email', 'owner@mail.ps')->firstOrFail();
        $this->assertSame(UserStatus::PendingVerification, $owner->status);
        $this->assertTrue($owner->hasRole(UserRole::WorkspaceOwner->value));

        $this->assertDatabaseHas('workspaces', [
            'owner_id' => $owner->id,
            'name' => 'My Space',
        ]);

        Notification::assertSentTo($admin, NewWorkspaceRegistrationNotification::class);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@mail.ps']);

        $this->postJson('/api/auth/register', [
            'name' => 'Dup',
            'email' => 'taken@mail.ps',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UserRole::Freelancer->value,
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_admin_role_is_not_registerable(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@mail.ps',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UserRole::Admin->value,
        ])->assertStatus(422)->assertJsonValidationErrors(['role']);
    }

    public function test_owner_registration_requires_documents_and_workspace_fields(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Owner Two',
            'email' => 'owner2@mail.ps',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UserRole::WorkspaceOwner->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['workspace_name', 'city_id', 'address', 'license_file', 'id_document']);
    }

    public function test_password_must_be_confirmed(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Mismatch',
            'email' => 'mismatch@mail.ps',
            'password' => 'password123',
            'password_confirmation' => 'different',
            'role' => UserRole::Freelancer->value,
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }
}
