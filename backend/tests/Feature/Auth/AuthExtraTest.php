<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\SeatType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Supplemental Auth coverage for gaps not exercised by the per-flow test
 * classes: the freelancer (role.freelancer) guard, logout token isolation +
 * SSO single-logout, the forgot-password throttle, and the onboarding
 * seat-setup endpoint authorization.
 */
class AuthExtraTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAppRoles;

    private const FREELANCER_ROUTE = '/api/booking-requests';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAppRoles();
    }

    private function token(User $user): string
    {
        return $user->createToken('auth_token')->plainTextToken;
    }

    // ---- role.freelancer (EnsureFreelancer) guard + status gating ----

    public function test_owner_is_forbidden_from_a_freelancer_route(): void
    {
        $owner = User::factory()->owner()->create();

        // Wrong role -> 403 (empty body POST also fails validation, but the
        // role guard runs first and short-circuits before the controller).
        $this->withToken($this->token($owner))
            ->postJson(self::FREELANCER_ROUTE, [])
            ->assertStatus(403);
    }

    public function test_suspended_freelancer_is_blocked_by_the_guard(): void
    {
        $freelancer = User::factory()->freelancer()->create([
            'status' => UserStatus::Suspended,
        ]);

        $this->withToken($this->token($freelancer))
            ->postJson(self::FREELANCER_ROUTE, [])
            ->assertStatus(403);
    }

    public function test_pending_freelancer_is_blocked_by_the_guard(): void
    {
        $freelancer = User::factory()->freelancer()->create([
            'status' => UserStatus::PendingVerification,
        ]);

        $this->withToken($this->token($freelancer))
            ->postJson(self::FREELANCER_ROUTE, [])
            ->assertStatus(403);
    }

    public function test_unauthenticated_request_to_freelancer_route_is_rejected(): void
    {
        $this->postJson(self::FREELANCER_ROUTE, [])->assertStatus(401);
    }

    // ---- logout token isolation + SSO single-logout ----

    public function test_logout_revokes_only_the_presented_token(): void
    {
        $user = User::factory()->create();
        // A second active session (e.g. another device) must survive.
        $user->createToken('other_device');
        $current = $user->createToken('auth_token')->plainTextToken;

        $this->assertSame(2, $user->tokens()->count());

        $this->withToken($current)->postJson('/api/auth/logout')->assertOk();

        // Exactly the current token is gone; the other session remains.
        $this->assertSame(1, $user->fresh()->tokens()->count());
    }

    public function test_logout_returns_sso_logout_url_for_an_sso_backed_session(): void
    {
        config([
            'services.taqat_sso.end_session_endpoint' => 'https://idp.example/logout',
            'services.taqat_sso.client_id' => 'taqat-client',
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('auth_token');

        // Mark the session as SSO-backed so buildLogoutUrl() emits an IdP URL.
        Cache::put('sso:session:'.$token->accessToken->getKey(), [
            'id_token' => 'id-token-value',
        ], 600);

        $this->withToken($token->plainTextToken)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.sso_logout_url', fn (?string $url): bool => is_string($url)
                && str_starts_with($url, 'https://idp.example/logout'));
    }

    // ---- forgot-password throttle (throttle:3,60) ----

    public function test_forgot_password_is_throttled_after_repeated_requests(): void
    {
        $payload = ['email' => 'rate@mail.ps'];

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/forgot-password', $payload)->assertOk();
        }

        // The 4th request within the window is rate-limited.
        $this->postJson('/api/auth/forgot-password', $payload)->assertStatus(429);
    }

    // ---- onboarding seats endpoint authorization ----

    public function test_onboarding_seats_creates_seats_for_a_pending_owner(): void
    {
        $owner = User::factory()->owner()->create([
            'status' => UserStatus::PendingVerification,
        ]);
        Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->withToken($this->token($owner))
            ->postJson('/api/auth/onboarding/seats', [
                'seats' => [
                    ['type' => SeatType::Fixed->value, 'count' => 2],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.seats');

        $this->assertDatabaseCount('seats', 2);
    }

    public function test_onboarding_seats_rejects_a_non_owner(): void
    {
        $freelancer = User::factory()->freelancer()->create();

        // OnboardingSeatsRequest::authorize() requires an owner with a workspace.
        $this->withToken($this->token($freelancer))
            ->postJson('/api/auth/onboarding/seats', [
                'seats' => [
                    ['type' => SeatType::Fixed->value, 'count' => 1],
                ],
            ])
            ->assertStatus(403);
    }

    public function test_onboarding_seats_requires_authentication(): void
    {
        $this->postJson('/api/auth/onboarding/seats', [
            'seats' => [['type' => SeatType::Fixed->value, 'count' => 1]],
        ])->assertStatus(401);
    }

    public function test_onboarding_seats_validates_the_payload(): void
    {
        $owner = User::factory()->owner()->create();
        Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->withToken($this->token($owner))
            ->postJson('/api/auth/onboarding/seats', ['seats' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['seats']);
    }

    // ---- role enum invariants relied on by the guards ----

    public function test_admin_role_is_never_registerable(): void
    {
        $this->assertNotContains(UserRole::Admin->value, UserRole::registerable());
        $this->assertContains(UserRole::Freelancer->value, UserRole::registerable());
        $this->assertContains(UserRole::WorkspaceOwner->value, UserRole::registerable());
    }
}
