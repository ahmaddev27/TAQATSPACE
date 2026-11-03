<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Auth\TaqatSsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function otp(User $user): string
    {
        return app(Google2FA::class)->getCurrentOtp($user->fresh()->two_factor_secret);
    }

    /** Authenticate as a bearer token the way a real client would. */
    private function withBearer(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    /** @return array{0: User, 1: list<string>} */
    private function enrolledOwner(): array
    {
        $user = User::factory()->owner()->create(['email' => 'owner@mail.ps', 'password' => 'password']);
        $token = $user->createToken('setup')->plainTextToken;

        $this->withBearer($token)->postJson('/api/auth/two-factor/enable')->assertOk();
        $codes = $this->withBearer($token)
            ->postJson('/api/auth/two-factor/confirm', ['code' => $this->otp($user)])
            ->assertOk()
            ->json('data.recovery_codes');

        return [$user->fresh(), $codes];
    }

    /** Password login followed by the challenge; returns the full-session token. */
    private function signInWithChallenge(User $user): string
    {
        $token = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->json('data.token');

        $this->withBearer($token)
            ->postJson('/api/auth/two-factor/challenge', ['code' => $this->otp($user)])
            ->assertOk();

        return $token;
    }

    public function test_enable_returns_secret_and_qr_and_stores_the_secret_encrypted(): void
    {
        $user = User::factory()->owner()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/auth/two-factor/enable')->assertOk();

        $secret = $response->json('data.secret');
        $this->assertStringContainsString('<svg', $response->json('data.qr_svg'));
        $this->assertStringStartsWith('otpauth://totp/', $response->json('data.otpauth_url'));

        $raw = DB::table('users')->where('id', $user->id)->value('two_factor_secret');
        $this->assertNotSame($secret, $raw);
        $this->assertSame($secret, $user->fresh()->two_factor_secret);
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_freelancers_cannot_enable_two_factor(): void
    {
        $user = User::factory()->freelancer()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/auth/two-factor/enable')->assertForbidden();
    }

    public function test_confirm_rejects_a_wrong_code_and_accepts_the_right_one(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/auth/two-factor/enable')->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/two-factor/confirm', ['code' => '000000'])
            ->assertStatus(422);

        $codes = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/two-factor/confirm', ['code' => $this->otp($user)])
            ->assertOk()
            ->json('data.recovery_codes');

        $this->assertCount(10, $codes);
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->assertCount(10, $user->fresh()->two_factor_recovery_codes);
        $this->assertNotContains($codes[0], $user->fresh()->two_factor_recovery_codes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.two_factor.enabled', 'actor_id' => $user->id]);
    }

    public function test_login_with_two_factor_returns_only_a_challenge_token(): void
    {
        [$user] = $this->enrolledOwner();

        $response = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.two_factor_required', true)
            ->assertJsonMissingPath('data.user');

        $token = $response->json('data.token');

        $this->withBearer($token)->getJson('/api/auth/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'two_factor_pending');
    }

    public function test_challenge_with_a_valid_code_upgrades_the_session(): void
    {
        [$user] = $this->enrolledOwner();
        $token = $this->signInWithChallenge($user);

        $this->withBearer($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_challenge_with_a_wrong_code_keeps_the_session_locked(): void
    {
        [$user] = $this->enrolledOwner();
        $token = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->json('data.token');

        $this->withBearer($token)
            ->postJson('/api/auth/two-factor/challenge', ['code' => '000000'])
            ->assertStatus(422);

        $this->withBearer($token)->getJson('/api/auth/me')->assertForbidden();
    }

    public function test_a_recovery_code_works_only_once(): void
    {
        [$user, $codes] = $this->enrolledOwner();

        $first = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->json('data.token');
        $this->withBearer($first)
            ->postJson('/api/auth/two-factor/challenge', ['recovery_code' => $codes[0]])
            ->assertOk();

        $second = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->json('data.token');
        $this->withBearer($second)
            ->postJson('/api/auth/two-factor/challenge', ['recovery_code' => $codes[0]])
            ->assertStatus(422);

        $this->assertCount(9, $user->fresh()->two_factor_recovery_codes);
    }

    public function test_five_wrong_codes_throttle_the_account(): void
    {
        [$user] = $this->enrolledOwner();
        $token = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->json('data.token');

        foreach (range(1, 5) as $_) {
            $this->withBearer($token)->postJson('/api/auth/two-factor/challenge', ['code' => '000000'])
                ->assertStatus(422);
        }

        $this->withBearer($token)
            ->postJson('/api/auth/two-factor/challenge', ['code' => $this->otp($user)])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_recent_two_factor_middleware_enforces_the_ten_minute_window(): void
    {
        Route::middleware(['api', 'auth:sanctum', '2fa.recent'])->get('/api/_test/step-up', fn () => response()->json(['ok' => true]));

        [$user] = $this->enrolledOwner();
        $token = $this->signInWithChallenge($user);

        $this->withBearer($token)->getJson('/api/_test/step-up')->assertOk();

        $this->travel(9)->minutes();
        $this->withBearer($token)->getJson('/api/_test/step-up')->assertOk();

        $this->travel(2)->minutes();
        $this->withBearer($token)->getJson('/api/_test/step-up')
            ->assertForbidden()
            ->assertJsonPath('code', 'two_factor_required');

        $this->withBearer($token)->postJson('/api/auth/two-factor/verify', ['code' => $this->otp($user)])->assertOk();
        $this->withBearer($token)->getJson('/api/_test/step-up')->assertOk();
    }

    public function test_recent_two_factor_middleware_does_not_block_users_without_two_factor(): void
    {
        Route::middleware(['api', 'auth:sanctum', '2fa.recent'])->get('/api/_test/step-up', fn () => response()->json(['ok' => true]));

        $this->actingAs(User::factory()->owner()->create(), 'sanctum')
            ->getJson('/api/_test/step-up')->assertOk();
    }

    public function test_disable_requires_a_valid_code_and_is_audited(): void
    {
        [$user] = $this->enrolledOwner();
        $token = $this->signInWithChallenge($user);

        $this->withBearer($token)->postJson('/api/auth/two-factor/disable', ['code' => '000000'])->assertStatus(422);
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->withBearer($token)->postJson('/api/auth/two-factor/disable', ['code' => $this->otp($user)])->assertOk();

        $fresh = $user->fresh();
        $this->assertFalse($fresh->hasTwoFactorEnabled());
        $this->assertNull($fresh->two_factor_secret);
        $this->assertSame(1, AuditLog::query()->where('action', 'auth.two_factor.disabled')->count());
    }

    public function test_sso_exchange_returns_a_challenge_token_for_users_with_two_factor(): void
    {
        $user = User::factory()->owner()->create();

        Cache::put('sso:exchange:c1', [
            'token' => 'tok', 'role' => $user->role->value, 'user_id' => $user->id, 'two_factor_required' => true,
        ], 60);

        $this->postJson('/api/auth/taqat/exchange', ['code' => 'c1'])
            ->assertOk()
            ->assertJsonPath('data.two_factor_required', true)
            ->assertJsonPath('data.token', 'tok')
            ->assertJsonMissingPath('data.user');
    }

    public function test_sso_mfa_claim_skips_the_local_challenge_only_when_trusted(): void
    {
        $sso = app(TaqatSsoService::class);

        $this->assertTrue($sso->assertsMfa(['sub' => 'x', 'amr' => ['pwd', 'mfa']]));
        $this->assertFalse($sso->assertsMfa(['sub' => 'x', 'amr' => ['pwd']]));
        $this->assertFalse($sso->assertsMfa(['sub' => 'x']));

        config(['services.taqat_sso.trust_mfa_claim' => false]);
        $this->assertFalse($sso->assertsMfa(['sub' => 'x', 'amr' => ['mfa']]));
    }

    public function test_status_reports_enrollment_state(): void
    {
        [$user] = $this->enrolledOwner();

        $this->actingAs($user, 'sanctum')->getJson('/api/auth/two-factor')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.recovery_codes_remaining', 10);
    }
}
