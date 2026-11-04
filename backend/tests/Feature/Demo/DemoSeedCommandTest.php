<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeedCommandTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Demo-Pass-For-Tests-1';

    protected function setUp(): void
    {
        parent::setUp();

        putenv('DEMO_PASSWORD='.self::PASSWORD);
        $_ENV['DEMO_PASSWORD'] = self::PASSWORD;
        $_SERVER['DEMO_PASSWORD'] = self::PASSWORD;
        $this->useEnvironment('testing', 'http://localhost');
    }

    protected function tearDown(): void
    {
        putenv('DEMO_PASSWORD');
        unset($_ENV['DEMO_PASSWORD'], $_SERVER['DEMO_PASSWORD']);

        parent::tearDown();
    }

    private function useEnvironment(string $environment, string $appUrl): void
    {
        $this->app['env'] = $environment;
        config(['app.url' => $appUrl]);
    }

    private function demoUserCount(): int
    {
        return User::query()->where('email', 'like', '%@demo.taqat.test')->count();
    }

    public function test_refuses_on_a_production_host_whatever_the_environment_says(): void
    {
        foreach (['production', 'staging', 'local', 'testing'] as $environment) {
            $this->useEnvironment($environment, 'https://api.taqat.space');

            $this->artisan('demo:seed')->assertFailed();
        }

        $this->useEnvironment('production', 'https://taqat.space');
        $this->artisan('demo:seed')->assertFailed();

        $this->assertSame(0, $this->demoUserCount());
    }

    public function test_refuses_a_staging_environment_pointing_at_a_production_url(): void
    {
        $this->useEnvironment('staging', 'https://api.taqat.space');

        $this->artisan('demo:seed')->assertFailed();
        $this->assertSame(0, User::query()->count());
    }

    public function test_refuses_a_production_environment_on_an_unknown_host(): void
    {
        $this->useEnvironment('production', 'https://example.org');

        $this->artisan('demo:seed')->assertFailed();
        $this->assertSame(0, User::query()->count());
    }

    public function test_allows_a_production_environment_on_a_staging_host(): void
    {
        $this->useEnvironment('production', 'https://api.staging.taqat.space');

        $this->artisan('demo:seed')->assertSuccessful();
        $this->assertGreaterThan(0, $this->demoUserCount());
    }

    public function test_refuses_without_a_password(): void
    {
        putenv('DEMO_PASSWORD');
        unset($_ENV['DEMO_PASSWORD'], $_SERVER['DEMO_PASSWORD']);

        $this->artisan('demo:seed')->assertFailed();
        $this->assertSame(0, User::query()->count());
    }

    public function test_never_prints_the_password(): void
    {
        $this->artisan('demo:seed')
            ->doesntExpectOutputToContain(self::PASSWORD)
            ->assertSuccessful();
    }

    public function test_seeds_every_role_and_marks_the_data_as_demo(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        $this->assertSame(0, User::query()->where('email', 'not like', '%@demo.taqat.test')->count());
        $this->assertSame(10, User::query()->where('role', UserRole::Freelancer)->count());
        $this->assertSame(3, User::query()->where('role', UserRole::WorkspaceOwner)->count());
        $this->assertSame(3, User::query()->where('role', UserRole::Cashier)->count());
        $this->assertSame(2, User::query()->where('role', UserRole::Admin)->count());
        $this->assertSame(0, Workspace::query()->where('slug', 'not like', 'demo-%')->count());
        $this->assertSame(0, User::query()->whereNotNull('phone')->count());
    }

    public function test_the_limited_admin_lacks_approve_requests_and_the_super_admin_has_everything(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        $limited = User::query()->where('email', 'admin.limited@demo.taqat.test')->firstOrFail();
        $super = User::query()->where('email', 'admin@demo.taqat.test')->firstOrFail();

        $this->assertNotContains('approve_requests', $limited->effectivePermissionNames());
        $this->assertContains('approve_requests', $super->effectivePermissionNames());
    }

    public function test_covers_every_subscription_and_invoice_status_with_a_partial_payment(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        foreach (SubscriptionStatus::cases() as $status) {
            $this->assertTrue(Subscription::query()->where('status', $status)->exists(), "no {$status->value} subscription");
        }

        foreach (InvoiceStatus::cases() as $status) {
            $this->assertTrue(Invoice::query()->where('status', $status)->exists(), "no {$status->value} invoice");
        }

        $partial = Invoice::query()->where('status', InvoiceStatus::PartiallyPaid)->firstOrFail();
        $this->assertEqualsWithDelta(15.0, (float) $partial->payments()->sum('amount'), 0.001);
        $this->assertGreaterThan(0, AuditLog::query()->count());
    }

    public function test_every_role_can_log_in_through_the_login_endpoint(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        $logins = [
            'admin@demo.taqat.test' => UserRole::Admin,
            'admin.limited@demo.taqat.test' => UserRole::Admin,
            'owner1@demo.taqat.test' => UserRole::WorkspaceOwner,
            'owner.throwaway@demo.taqat.test' => UserRole::WorkspaceOwner,
            'staff.cashier@demo.taqat.test' => UserRole::Cashier,
            'staff.finance@demo.taqat.test' => UserRole::Cashier,
            'freelancer1@demo.taqat.test' => UserRole::Freelancer,
            'freelancer10@demo.taqat.test' => UserRole::Freelancer,
        ];

        foreach ($logins as $email => $role) {
            $this->postJson('/api/auth/login', ['email' => $email, 'password' => self::PASSWORD])
                ->assertOk()
                ->assertJsonPath('data.role', $role->value);
        }
    }

    public function test_a_second_run_without_fresh_changes_nothing(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();
        $users = User::query()->count();

        $this->artisan('demo:seed')->assertSuccessful();

        $this->assertSame($users, User::query()->count());
    }

    public function test_fresh_is_idempotent(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();
        $counts = $this->tableCounts();

        $this->artisan('demo:seed --fresh')->assertSuccessful();
        $this->artisan('demo:seed --fresh')->assertSuccessful();

        $this->assertSame($counts, $this->tableCounts());
    }

    public function test_fresh_leaves_non_demo_data_untouched(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'real.owner@mail.ps']);
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'name' => 'Real workspace']);
        $member = User::factory()->freelancer()->create(['email' => 'real.member@mail.ps']);
        Subscription::factory()->create(['member_id' => $member->id, 'workspace_id' => $workspace->id]);
        AuditLog::query()->create(['workspace_id' => $workspace->id, 'actor_type' => 'system', 'action' => 'real.action']);

        $this->artisan('demo:seed')->assertSuccessful();
        $this->artisan('demo:seed --fresh')->assertSuccessful();

        $this->assertNotNull(Workspace::query()->find($workspace->id));
        $this->assertNotNull(User::query()->find($owner->id));
        $this->assertNotNull(User::query()->find($member->id));
        $this->assertSame(1, Subscription::query()->where('workspace_id', $workspace->id)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'real.action')->count());
    }

    /** @return array<string, int> */
    private function tableCounts(): array
    {
        return [
            'users' => User::query()->count(),
            'workspaces' => Workspace::query()->count(),
            'subscriptions' => Subscription::query()->count(),
            'invoices' => Invoice::query()->count(),
            'audit' => AuditLog::query()->count(),
        ];
    }
}
