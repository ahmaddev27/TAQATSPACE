<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\FinancePermission;
use App\Enums\PosPermission;
use App\Enums\StaffGroup;
use App\Models\Subscription;
use App\Models\UnbilledCharge;
use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Staff finance permissions: endpoint x permission matrix, owner implicit
 * access, the 2FA step-up on issue/void, and the legacy POS grants.
 */
class FinancePermissionsTest extends CatalogTestCase
{
    private const CHARGES = '/api/workspace/finance/unbilled-charges';

    private const ITEMS = '/api/workspace/finance/catalog/items';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['collect', 'issue-void', 'reports', 'expenses'] as $slug) {
            $permission = str_replace('-', '_', $slug);
            Route::middleware(['api', 'auth:sanctum', 'finance.permission:finance_'.$permission])
                ->match(['GET', 'POST'], "/api/_test/finance/{$slug}", fn () => response()->json(['ok' => true]));
        }

        Route::middleware(['api', 'auth:sanctum', 'finance.permission:finance_issue_void', '2fa.recent'])
            ->post('/api/_test/finance/step-up', fn () => response()->json(['ok' => true]));
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> endpoint permission matrix */
    public static function matrix(): array
    {
        return [
            'collect' => ['post', '/api/_test/finance/collect', 'finance_collect', 'finance_reports'],
            'reports' => ['get', '/api/_test/finance/reports', 'finance_reports', 'finance_collect'],
            'expenses' => ['post', '/api/_test/finance/expenses', 'finance_expenses', 'finance_reports'],
            'issue void' => ['post', '/api/_test/finance/issue-void', 'finance_issue_void', 'finance_collect'],
        ];
    }

    #[DataProvider('matrix')]
    public function test_endpoint_needs_exactly_its_permission(string $verb, string $url, string $granted, string $other): void
    {
        [$owner, $workspace] = $this->owningWorkspace();

        Sanctum::actingAs($this->cashierFor($workspace, []));
        $this->json($verb, $url)->assertForbidden();

        Sanctum::actingAs($this->cashierFor($workspace, [$other]));
        $this->json($verb, $url)->assertForbidden();

        Sanctum::actingAs($this->cashierFor($workspace, [$granted]));
        $this->json($verb, $url)->assertOk();

        Sanctum::actingAs($owner);
        $this->json($verb, $url)->assertOk();
    }

    public function test_unauthenticated_and_members_are_rejected(): void
    {
        $this->postJson('/api/_test/finance/collect')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/_test/finance/collect')->assertForbidden();
    }

    public function test_reports_only_staff_can_read_but_never_write(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $member = User::factory()->create();
        Subscription::factory()->create(['member_id' => $member->id, 'workspace_id' => $workspace->id]);
        $charge = UnbilledCharge::factory()->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($this->cashierFor($workspace, [FinancePermission::Reports->value]));

        $this->getJson('/api/_test/finance/reports')->assertOk();
        $this->getJson(self::CHARGES)->assertOk();
        $this->postJson(self::CHARGES, ['customer_id' => $member->id, 'description' => 'x', 'qty' => 1, 'unit_price' => 1])->assertForbidden();
        $this->deleteJson(self::CHARGES.'/'.$charge->id)->assertForbidden();
        $this->postJson(self::ITEMS, ['name' => 'x'])->assertForbidden();
        $this->postJson('/api/_test/finance/collect')->assertForbidden();
    }

    public function test_withdrawals_and_items_permissions_unlock_their_own_endpoints_only(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $member = User::factory()->create();
        Subscription::factory()->create(['member_id' => $member->id, 'workspace_id' => $workspace->id]);
        $charge = ['customer_id' => $member->id, 'description' => 'Tea', 'qty' => 1, 'unit_price' => 2];

        Sanctum::actingAs($this->cashierFor($workspace, [FinancePermission::Withdrawals->value]));
        $this->postJson(self::CHARGES, $charge)->assertSuccessful();
        $this->postJson(self::ITEMS, ['name' => 'x'])->assertForbidden();

        Sanctum::actingAs($this->cashierFor($workspace, [FinancePermission::Items->value]));
        $this->postJson(self::CHARGES, $charge)->assertForbidden();
        $this->postJson(self::ITEMS, ['name' => 'x'])->assertStatus(422);
    }

    public function test_legacy_pos_grants_keep_working(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $member = User::factory()->create();
        Subscription::factory()->create(['member_id' => $member->id, 'workspace_id' => $workspace->id]);

        Sanctum::actingAs($this->cashierFor($workspace, [PosPermission::ManageProducts->value]));
        $this->postJson(self::CHARGES, ['customer_id' => $member->id, 'description' => 'Tea', 'qty' => 1, 'unit_price' => 2])
            ->assertSuccessful();

        Sanctum::actingAs($this->cashierFor($workspace, PosPermission::defaultsForCashier()));
        $this->getJson('/api/_test/finance/reports')->assertOk();
        $this->postJson('/api/_test/finance/collect')->assertForbidden();
    }

    public function test_issue_void_needs_a_recent_two_factor_even_with_the_permission(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $staff = $this->cashierFor($workspace, [FinancePermission::IssueVoid->value]);
        $staff->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();
        Sanctum::actingAs($staff);

        $this->postJson('/api/_test/finance/step-up')
            ->assertForbidden()
            ->assertJsonPath('code', 'two_factor_required');

        app(TwoFactorService::class)->markVerified($staff);
        $this->postJson('/api/_test/finance/step-up')->assertOk();

        // The 2FA window never replaces the permission itself.
        $other = $this->cashierFor($workspace, []);
        $other->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();
        app(TwoFactorService::class)->markVerified($other);
        Sanctum::actingAs($other);
        $this->postJson('/api/_test/finance/step-up')->assertForbidden();
    }

    public function test_group_map_and_gates_delegate_to_the_same_permissions(): void
    {
        $this->assertEqualsCanonicalizing(
            ['pos_sell', 'pos_view_reports', 'finance_collect', 'finance_view_pos'],
            StaffGroup::Cashier->permissions(),
        );

        $supervisor = StaffGroup::FinanceSupervisor->permissions();
        $this->assertContains('finance_reports', $supervisor);
        $this->assertContains('finance_issue_void', $supervisor);
        $this->assertNotContains('finance_import', $supervisor);

        [, $workspace] = $this->owningWorkspace();
        $this->actingAs($this->cashierFor($workspace, ['finance_collect']));
        $this->assertTrue(Gate::allows('finance.finance_collect'));
        $this->assertFalse(Gate::allows('finance.finance_reports'));
    }
}
