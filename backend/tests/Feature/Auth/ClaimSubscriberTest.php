<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Auth\ClaimConflictException;
use App\Services\Auth\TaqatSsoService;
use App\Services\Auth\UnclaimedSubscriberService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaimSubscriberTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAppRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAppRoles();
    }

    private function unclaimed(array $data): User
    {
        return app(UnclaimedSubscriberService::class)->create($data + ['name' => 'Imported']);
    }

    private function signIn(array $claims): User
    {
        return app(TaqatSsoService::class)->findOrProvisionUser($claims + ['sub' => 'sub-1']);
    }

    public function test_unclaimed_subscriber_has_no_credentials_and_is_audited(): void
    {
        $user = $this->unclaimed(['email' => 'Imp@Mail.ps', 'phone' => '+970 59-000 0001']);

        $this->assertTrue($user->isUnclaimed());
        $this->assertNull($user->sso_sub);
        $this->assertNull($user->password);
        $this->assertSame('imp@mail.ps', $user->email);
        $this->assertSame('+970590000001', $user->phone);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscriber.unclaimed.created', 'subject_id' => $user->id]);
    }

    public function test_unclaimed_subscriber_needs_an_identifier(): void
    {
        $this->expectException(DomainException::class);

        $this->unclaimed(['email' => null, 'phone' => null]);
    }

    public function test_phone_only_subscriber_can_be_created_and_listed_with_its_subscriptions(): void
    {
        $user = $this->unclaimed(['phone' => '0590000002']);
        Subscription::factory()->create(['member_id' => $user->id]);

        $this->assertNull($user->email);
        $this->assertSame(1, $user->subscriptions()->count());
        $this->assertTrue(User::query()->whereHas('subscriptions')->whereKey($user->id)->exists());
    }

    public function test_email_match_links_sso_sub_to_the_same_record_keeping_subscriptions(): void
    {
        $user = $this->unclaimed(['email' => 'match@mail.ps']);
        $subscription = Subscription::factory()->create(['member_id' => $user->id]);

        $merged = $this->signIn(['email' => 'match@mail.ps', 'name' => 'Real Name']);

        $this->assertSame($user->id, $merged->id);
        $this->assertSame('sub-1', $merged->sso_sub);
        $this->assertFalse($merged->isUnclaimed());
        $this->assertSame(1, User::query()->where('email', 'match@mail.ps')->count());
        $this->assertSame($user->id, $subscription->fresh()->member_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscriber.claimed', 'subject_id' => $user->id]);
    }

    public function test_phone_match_requires_a_verified_phone(): void
    {
        $user = $this->unclaimed(['phone' => '+970590000003']);

        $fresh = $this->signIn(['email' => 'other@mail.ps', 'phone_number' => '+970 590000003']);
        $this->assertNotSame($user->id, $fresh->id);
        $this->assertTrue($user->fresh()->isUnclaimed());

        $merged = $this->signIn([
            'sub' => 'sub-2',
            'email' => 'third@mail.ps',
            'phone_number' => '+970 590000003',
            'phone_number_verified' => true,
        ]);

        $this->assertSame($user->id, $merged->id);
        $this->assertSame('sub-2', $merged->sso_sub);
        $this->assertSame('third@mail.ps', $merged->email ?? 'third@mail.ps');
    }

    public function test_multiple_phone_candidates_are_logged_and_not_merged(): void
    {
        $a = $this->unclaimed(['phone' => '+970590000004', 'name' => 'A']);
        $b = $this->unclaimed(['phone' => '+970590000004', 'name' => 'B']);

        $new = $this->signIn(['email' => 'conflict@mail.ps', 'phone_number' => '+970590000004', 'phone_number_verified' => true]);

        $this->assertNotContains($new->id, [$a->id, $b->id]);
        $this->assertTrue($a->fresh()->isUnclaimed());
        $this->assertTrue($b->fresh()->isUnclaimed());
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscriber.claim.conflict']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'subscriber.claimed']);
    }

    public function test_email_and_phone_pointing_at_different_records_is_a_blocking_conflict(): void
    {
        $byEmail = $this->unclaimed(['email' => 'e@mail.ps']);
        $byPhone = $this->unclaimed(['phone' => '+970590000005']);

        try {
            $this->signIn(['email' => 'e@mail.ps', 'phone_number' => '+970590000005', 'phone_number_verified' => true]);
            $this->fail('Expected a claim conflict.');
        } catch (ClaimConflictException) {
            // expected
        }

        $this->assertTrue($byEmail->fresh()->isUnclaimed());
        $this->assertTrue($byPhone->fresh()->isUnclaimed());
        $this->assertSame(1, AuditLog::query()->where('action', 'subscriber.claim.conflict')->count());
    }

    public function test_no_match_provisions_a_new_claimed_account(): void
    {
        $this->unclaimed(['email' => 'someone@mail.ps']);

        $user = $this->signIn(['email' => 'nomatch@mail.ps', 'phone_number' => '+970590000006']);

        $this->assertFalse($user->isUnclaimed());
        $this->assertSame('nomatch@mail.ps', $user->email);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'subscriber.claimed']);
    }

    public function test_unverified_email_does_not_merge(): void
    {
        $user = $this->unclaimed(['email' => 'unverified@mail.ps']);

        $this->expectException(\RuntimeException::class);

        try {
            $this->signIn(['email' => 'unverified@mail.ps', 'email_verified' => false]);
        } finally {
            $this->assertTrue($user->fresh()->isUnclaimed());
        }
    }

    public function test_unclaimed_user_cannot_log_in_or_reset_password(): void
    {
        $this->unclaimed(['email' => 'nologin@mail.ps']);

        $this->postJson('/api/auth/login', ['email' => 'nologin@mail.ps', 'password' => 'password'])
            ->assertStatus(401);
        $this->postJson('/api/auth/login', ['email' => 'nologin@mail.ps', 'password' => ''])
            ->assertStatus(422);

        $this->postJson('/api/auth/forgot-password', ['email' => 'nologin@mail.ps']);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }
}
