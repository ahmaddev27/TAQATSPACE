<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Auth\TaqatSsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SsoTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAppRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAppRoles();
    }

    private function service(): TaqatSsoService
    {
        return app(TaqatSsoService::class);
    }

    public function test_exchange_redeems_a_valid_one_time_code_for_a_token(): void
    {
        $user = User::factory()->create();

        Cache::put('sso:exchange:good-code', [
            'token' => 'plain-token-value',
            'role' => $user->role->value,
            'user_id' => $user->id,
        ], 60);

        $this->postJson('/api/auth/taqat/exchange', ['code' => 'good-code'])
            ->assertOk()
            ->assertJsonPath('data.token', 'plain-token-value')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id);

        // One-time: the cache entry is pulled (consumed) on redemption.
        $this->assertFalse(Cache::has('sso:exchange:good-code'));
    }

    public function test_exchange_rejects_a_missing_code(): void
    {
        $this->postJson('/api/auth/taqat/exchange', ['code' => ''])
            ->assertStatus(401);
    }

    public function test_exchange_rejects_an_unknown_code(): void
    {
        $this->postJson('/api/auth/taqat/exchange', ['code' => 'nope'])
            ->assertStatus(401);
    }

    public function test_exchange_rejects_code_for_a_deleted_user(): void
    {
        Cache::put('sso:exchange:orphan', [
            'token' => 'tok',
            'role' => UserRole::Freelancer->value,
            'user_id' => '00000000-0000-0000-0000-000000000000',
        ], 60);

        $this->postJson('/api/auth/taqat/exchange', ['code' => 'orphan'])
            ->assertStatus(401);
    }

    public function test_provision_creates_a_pending_user_awaiting_onboarding(): void
    {
        $user = $this->service()->findOrProvisionUser([
            'sub' => 'taqat-sub-1',
            'email' => 'newsso@mail.ps',
            'name' => 'New SSO',
            'phone_number' => '+970590000000',
        ]);

        $this->assertSame('newsso@mail.ps', $user->email);
        $this->assertSame('taqat-sub-1', $user->sso_sub);
        // Provisioned accounts are pending and must onboard (pick a role).
        $this->assertSame(UserStatus::PendingVerification, $user->status);
        $this->assertSame(UserRole::Freelancer, $user->role);
        $this->assertTrue($user->needsOnboarding());
        $this->assertTrue($user->hasRole(UserRole::Freelancer->value));
    }

    public function test_provision_links_an_existing_account_by_email_and_backfills_sub(): void
    {
        $existing = User::factory()->create([
            'email' => 'existing@mail.ps',
            'sso_sub' => null,
        ]);

        $user = $this->service()->findOrProvisionUser([
            'sub' => 'taqat-sub-2',
            'email' => 'existing@mail.ps',
            'name' => 'Existing',
        ]);

        $this->assertSame($existing->id, $user->id);
        $this->assertSame('taqat-sub-2', $user->fresh()->sso_sub);
    }

    public function test_provision_without_email_for_new_user_throws(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->service()->findOrProvisionUser([
            'sub' => 'no-email-sub',
            'name' => 'No Email',
        ]);
    }
}
