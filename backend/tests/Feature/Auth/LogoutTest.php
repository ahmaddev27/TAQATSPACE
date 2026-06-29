<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $plain = $user->createToken('auth_token')->plainTextToken;

        $this->assertSame(1, $user->tokens()->count());

        $response = $this->withToken($plain)->postJson('/api/auth/logout');

        $response->assertOk()
            // Password sessions have no upstream IdP, so no SSO logout URL.
            ->assertJsonPath('data.sso_logout_url', null);

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/auth/logout')->assertStatus(401);
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $plain = $user->createToken('auth_token')->plainTextToken;

        $this->withToken($plain)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }
}
