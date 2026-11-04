<?php

declare(strict_types=1);

namespace Tests\Feature\Members;

use App\Enums\EducationLevel;
use App\Models\AuditLog;
use App\Models\HotspotAccount;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        $this->owner = User::factory()->owner()->create();
        $this->workspace = Workspace::factory()->create(['owner_id' => $this->owner->id]);
        $this->subscription = Subscription::factory()->create(['workspace_id' => $this->workspace->id]);
    }

    private function url(?User $member = null): string
    {
        return '/api/workspace/members/'.($member ?? $this->subscription->member)->id.'/profile';
    }

    private function provision(Subscription $subscription, string $username = 'ali.one'): HotspotAccount
    {
        return HotspotAccount::query()->withoutWorkspaceScope()->create([
            'workspace_id' => $subscription->workspace_id,
            'subscription_id' => $subscription->id,
            'username' => $username,
            'password_enc' => 'secret123',
        ]);
    }

    public function test_guest_and_freelancer_cannot_update_a_profile(): void
    {
        $this->putJson($this->url(), [])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->freelancer()->create());
        $this->putJson($this->url(), [])->assertForbidden();
    }

    public function test_owner_saves_donor_fields_and_completion_rises(): void
    {
        $this->provision($this->subscription);
        Sanctum::actingAs($this->owner);

        $this->putJson($this->url(), [
            'gender' => 'female',
            'education_level' => 'bachelor',
            'specialization' => 'nursing',
            'employment_status' => 'student',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.education_level', 'bachelor')
            ->assertJsonPath('data.user.specialization', 'nursing')
            ->assertJsonPath('data.mikrotik_username', 'ali.one')
            ->assertJsonPath('data.profile_completion.percent', 100)
            ->assertJsonPath('data.profile_completion.missing', []);

        $this->assertSame(EducationLevel::Bachelor, $this->subscription->member->fresh()->education_level);
    }

    public function test_values_outside_the_restricted_lists_are_rejected(): void
    {
        Sanctum::actingAs($this->owner);

        $this->putJson($this->url(), [
            'gender' => 'robot',
            'education_level' => 'wizard',
            'specialization' => 'alchemy',
            'employment_status' => 'astronaut',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['gender', 'education_level', 'specialization', 'employment_status']);
    }

    public function test_member_of_another_workspace_is_not_found(): void
    {
        $foreign = Subscription::factory()->create();
        Sanctum::actingAs($this->owner);

        $this->putJson($this->url($foreign->member), ['gender' => 'male'])->assertNotFound();
        $this->assertNull($foreign->member->fresh()->gender);
    }

    public function test_legacy_specialty_is_left_untouched(): void
    {
        $this->subscription->member->update(['specialty' => 'Plumber']);
        Sanctum::actingAs($this->owner);

        $this->putJson($this->url(), ['specialization' => 'civil_engineering'])
            ->assertOk()
            ->assertJsonPath('data.user.specialty', 'Plumber');
    }

    public function test_mikrotik_username_is_renamed_and_audited(): void
    {
        $account = $this->provision($this->subscription);
        Sanctum::actingAs($this->owner);

        $this->putJson($this->url(), ['mikrotik_username' => 'new_user'])
            ->assertOk()
            ->assertJsonPath('data.mikrotik_username', 'new_user');

        $this->assertSame('new_user', $account->fresh()->username);
        $this->assertTrue(AuditLog::query()->where('action', 'network.hotspot_account.renamed')->exists());
    }

    public function test_duplicate_or_malformed_mikrotik_username_is_rejected(): void
    {
        $this->provision($this->subscription);
        $other = Subscription::factory()->create(['workspace_id' => $this->workspace->id]);
        $this->provision($other, 'taken');
        Sanctum::actingAs($this->owner);

        $this->putJson($this->url(), ['mikrotik_username' => 'taken'])
            ->assertUnprocessable()->assertJsonValidationErrors('mikrotik_username');
        $this->putJson($this->url(), ['mikrotik_username' => 'bad name!'])
            ->assertUnprocessable()->assertJsonValidationErrors('mikrotik_username');
    }

    public function test_username_cannot_be_set_without_an_internet_account(): void
    {
        Sanctum::actingAs($this->owner);

        $this->putJson($this->url(), ['mikrotik_username' => 'someone'])
            ->assertUnprocessable()->assertJsonValidationErrors('mikrotik_username');
    }

    public function test_incomplete_filter_lists_only_incomplete_members(): void
    {
        $this->provision($this->subscription);
        $this->subscription->member->update([
            'gender' => 'male', 'education_level' => 'master',
            'specialization' => 'law', 'employment_status' => 'employed',
        ]);
        $incomplete = Subscription::factory()->create(['workspace_id' => $this->workspace->id]);
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/workspace/members?profile=incomplete')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.members.0.user.id', $incomplete->member_id)
            ->assertJsonPath('data.members.0.profile_completion.percent', 0);
    }
}
