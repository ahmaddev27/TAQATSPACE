<?php

declare(strict_types=1);

namespace Tests\Feature\Internet;

use App\Enums\HotspotAccountStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\HotspotAccount;
use App\Models\InternetPackage;
use App\Models\Router;
use App\Models\RouterCommand;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Hotspot\HotspotAccountService;
use App\Services\Hotspot\LegacyCredentialsMigrator;
use App\Services\InternetAccessService;
use App\Services\MemberService;
use App\Services\PackageService;
use App\Services\SubscriptionLifecycleService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The subscription lifecycle (approval, suspension, expiry, renewal) is
 * mirrored onto the workspace's routers through the command queue.
 */
class HotspotAccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        $this->workspace = Workspace::factory()->create();
        $this->subscription = $this->subscriptionIn($this->workspace);
    }

    private function subscriptionIn(Workspace $workspace, array $attributes = []): Subscription
    {
        return Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => User::factory()->freelancer()->create(['name' => 'Same Name'])->id,
            'status' => SubscriptionStatus::Active->value,
            ...$attributes,
        ]);
    }

    /** @return list<string> verbs in creation order */
    private function verbs(): array
    {
        return RouterCommand::query()->withoutWorkspaceScope()->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (RouterCommand $command): string => $command->verb->value)->all();
    }

    public function test_provisioning_creates_the_account_and_pushes_profile_and_user(): void
    {
        $router = Router::factory()->create(['workspace_id' => $this->workspace->id]);
        $package = InternetPackage::factory()->create(['workspace_id' => $this->workspace->id, 'speed_mbps' => 20]);
        app(PackageService::class)->assignMember($package, $this->subscription->member_id);

        $result = app(InternetAccessService::class)->provision($this->subscription);

        $account = HotspotAccount::query()->withoutWorkspaceScope()->where('subscription_id', $this->subscription->id)->firstOrFail();
        $this->assertSame(HotspotAccountStatus::Active, $account->status);
        $this->assertSame('cws-'.substr(str_replace('-', '', $package->id), 0, 12), $account->profile);

        $commands = RouterCommand::query()->withoutWorkspaceScope()->where('router_id', $router->id)->get()->keyBy(fn ($c) => $c->verb->value);
        $this->assertEqualsCanonicalizing(['upsert-profile', 'upsert-user'], $commands->keys()->all());
        $this->assertSame('20M/20M', $commands['upsert-profile']->payload['rate_limit']);
        $this->assertSame($result['username'], $commands['upsert-user']->payload['username']);
        $this->assertSame($account->profile, $commands['upsert-user']->payload['profile']);

        $raw = DB::table('router_commands')->where('verb', 'upsert-user')->value('payload');
        $this->assertStringNotContainsString($result['password'], $raw);
        $this->assertStringNotContainsString($result['password'], (string) AuditLog::query()->get()->toJson());
    }

    public function test_accounts_are_saved_and_the_push_is_deferred_without_a_router(): void
    {
        app(InternetAccessService::class)->provision($this->subscription);

        $this->assertSame(1, HotspotAccount::query()->withoutWorkspaceScope()->count());
        $this->assertSame(0, RouterCommand::query()->withoutWorkspaceScope()->count());
    }

    public function test_suspending_a_member_disables_the_user_and_resuming_upserts_it(): void
    {
        Router::factory()->create(['workspace_id' => $this->workspace->id]);
        app(InternetAccessService::class)->provision($this->subscription);
        RouterCommand::query()->withoutWorkspaceScope()->delete();

        app(MemberService::class)->updateStatus($this->workspace, $this->subscription->member, 'suspended');

        $this->assertSame(['disable-user', 'remove-active'], $this->verbs());
        $this->assertSame(HotspotAccountStatus::Suspended, HotspotAccount::query()->withoutWorkspaceScope()->firstOrFail()->status);

        app(MemberService::class)->updateStatus($this->workspace, $this->subscription->member, 'active');

        $this->assertSame(['disable-user', 'remove-active', 'upsert-user'], $this->verbs());
        $this->assertSame(HotspotAccountStatus::Active, HotspotAccount::query()->withoutWorkspaceScope()->firstOrFail()->status);
    }

    public function test_expiry_disables_the_users_of_expired_subscriptions(): void
    {
        Router::factory()->create(['workspace_id' => $this->workspace->id]);
        $this->subscription->update(['end_date' => now()->subDays(2)->toDateString()]);
        app(InternetAccessService::class)->provision($this->subscription);
        RouterCommand::query()->withoutWorkspaceScope()->delete();

        app(SubscriptionLifecycleService::class)->expireDue();

        $this->assertSame(['disable-user', 'remove-active'], $this->verbs());
        $this->assertSame(HotspotAccountStatus::Suspended, HotspotAccount::query()->withoutWorkspaceScope()->firstOrFail()->status);
    }

    public function test_removing_disables_the_user_and_frees_the_username(): void
    {
        Router::factory()->create(['workspace_id' => $this->workspace->id]);
        app(InternetAccessService::class)->provision($this->subscription);

        app(HotspotAccountService::class)->remove($this->subscription);

        $this->assertSame(0, HotspotAccount::query()->withoutWorkspaceScope()->count());
        $this->assertContains('disable-user', $this->verbs());
    }

    public function test_lifecycle_events_are_audited(): void
    {
        app(InternetAccessService::class)->provision($this->subscription);
        app(HotspotAccountService::class)->suspend($this->subscription);

        $actions = AuditLog::query()->pluck('action')->all();
        $this->assertContains('network.hotspot_account.provisioned', $actions);
        $this->assertContains('network.hotspot_account.suspended', $actions);
    }

    public function test_usernames_are_unique_per_workspace_but_may_repeat_across_workspaces(): void
    {
        $service = app(InternetAccessService::class);
        $other = $this->subscriptionIn($this->workspace);
        $foreign = $this->subscriptionIn(Workspace::factory()->create());

        $first = $service->provision($this->subscription)['username'];
        $second = $service->provision($other)['username'];
        $third = $service->provision($foreign)['username'];

        $this->assertNotSame($first, $second);
        $this->assertSame($first, $third);

        $this->expectException(QueryException::class);
        HotspotAccount::query()->withoutWorkspaceScope()->create([
            'workspace_id' => $this->workspace->id,
            'subscription_id' => $this->subscriptionIn($this->workspace)->id,
            'username' => $first,
            'password_enc' => 'secret12',
        ]);
    }

    public function test_the_legacy_credentials_migration_moves_everything_and_is_rerunnable(): void
    {
        $this->subscription->forceFill(['internet_username' => 'legacy1', 'internet_password_enc' => 'pw123456', 'internet_provisioned_at' => now()])->save();
        $migrator = new LegacyCredentialsMigrator;

        $this->assertSame(1, $migrator->migrate());
        $this->assertSame(0, $migrator->migrate());

        $account = HotspotAccount::query()->withoutWorkspaceScope()->firstOrFail();
        $this->assertSame('legacy1', $account->username);
        $this->assertSame('pw123456', $account->password_enc);
        $this->assertSame(1, HotspotAccount::query()->withoutWorkspaceScope()->count());
    }
}
