<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the role.owner / EnsureWorkspaceOwner guard plus account-status
 * gating through an owner-only route.
 */
class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER_ROUTE = '/api/workspace/resources';

    private function token(User $user): string
    {
        return $user->createToken('auth_token')->plainTextToken;
    }

    public function test_active_owner_passes_the_guard(): void
    {
        $owner = User::factory()->owner()->create();

        $this->withToken($this->token($owner))
            ->getJson(self::OWNER_ROUTE)
            ->assertOk();
    }

    public function test_freelancer_is_forbidden_from_owner_route(): void
    {
        $freelancer = User::factory()->freelancer()->create();

        $this->withToken($this->token($freelancer))
            ->getJson(self::OWNER_ROUTE)
            ->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson(self::OWNER_ROUTE)->assertStatus(401);
    }

    public function test_suspended_owner_is_forbidden_before_role_check(): void
    {
        $owner = User::factory()->owner()->create([
            'status' => UserStatus::Suspended,
        ]);

        $this->withToken($this->token($owner))
            ->getJson(self::OWNER_ROUTE)
            ->assertStatus(403);
    }

    public function test_pending_owner_is_forbidden_before_role_check(): void
    {
        $owner = User::factory()->owner()->create([
            'status' => UserStatus::PendingVerification,
        ]);

        $this->withToken($this->token($owner))
            ->getJson(self::OWNER_ROUTE)
            ->assertStatus(403);
    }
}
