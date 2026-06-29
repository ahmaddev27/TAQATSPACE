<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_return_token_and_user(): void
    {
        $user = User::factory()->freelancer()->create([
            'email' => 'jane@mail.ps',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'jane@mail.ps',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.role', UserRole::Freelancer->value)
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_wrong_password_is_rejected_with_401(): void
    {
        User::factory()->create([
            'email' => 'jane@mail.ps',
            'password' => 'password',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'jane@mail.ps',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_unknown_email_is_rejected_with_401(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'nobody@mail.ps',
            'password' => 'password',
        ])->assertStatus(401);
    }

    public function test_suspended_account_is_forbidden(): void
    {
        User::factory()->suspended()->create([
            'email' => 'sus@mail.ps',
            'password' => 'password',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'sus@mail.ps',
            'password' => 'password',
        ])->assertStatus(403);
    }

    public function test_pending_verification_account_is_forbidden(): void
    {
        User::factory()->create([
            'email' => 'pending@mail.ps',
            'password' => 'password',
            'status' => UserStatus::PendingVerification,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'pending@mail.ps',
            'password' => 'password',
        ])->assertStatus(403);
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
