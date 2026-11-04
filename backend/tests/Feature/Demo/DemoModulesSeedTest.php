<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Enums\CatalogKind;
use App\Enums\ChequeStatus;
use App\Enums\DocStatus;
use App\Enums\HourPurchaseStatus;
use App\Enums\PricingMethod;
use App\Enums\UnbilledChargeStatus;
use App\Enums\WorkspaceFeature;
use App\Models\CatalogCategory;
use App\Models\CatalogItem;
use App\Models\Expense;
use App\Models\HotspotAccount;
use App\Models\HotspotSession;
use App\Models\HourPackage;
use App\Models\HourPackagePurchase;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Receipt;
use App\Models\Router;
use App\Models\Seat;
use App\Models\UnbilledCharge;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Demo\DemoMarker;
use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The demo data of milestones 2 to 5: flag split, network, stations, finance v2 and hour packages. */
class DemoModulesSeedTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Demo-Pass-For-Tests-1';

    protected function setUp(): void
    {
        parent::setUp();

        putenv('DEMO_PASSWORD='.self::PASSWORD);
        $_ENV['DEMO_PASSWORD'] = self::PASSWORD;
        $_SERVER['DEMO_PASSWORD'] = self::PASSWORD;
        $this->app['env'] = 'testing';
        config(['app.url' => 'http://localhost']);
    }

    protected function tearDown(): void
    {
        putenv('DEMO_PASSWORD');
        unset($_ENV['DEMO_PASSWORD'], $_SERVER['DEMO_PASSWORD']);

        parent::tearDown();
    }

    private function workspace(string $slug): Workspace
    {
        return Workspace::query()->where('slug', 'demo-'.$slug)->firstOrFail();
    }

    private function seedDemo(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();
    }

    public function test_the_first_and_the_two_factor_tenant_have_both_modules_on_and_the_others_have_none(): void
    {
        $this->seedDemo();
        $features = app(FeatureFlagService::class);

        foreach (['almanara', 'stepup'] as $slug) {
            $this->assertTrue($features->isEnabled($this->workspace($slug)->id, WorkspaceFeature::Network), $slug);
            $this->assertTrue($features->isEnabled($this->workspace($slug)->id, WorkspaceFeature::FinanceV2), $slug);
        }

        foreach (['ibdaa', 'throwaway'] as $slug) {
            $this->assertSame([false, false, false], array_values($features->flagsFor($this->workspace($slug)->id)), $slug);
        }
    }

    public function test_enabling_finance_v2_seeded_the_expense_categories_of_the_first_tenant(): void
    {
        $this->seedDemo();

        $this->assertTrue(CatalogCategory::query()->withoutWorkspaceScope()
            ->where('workspace_id', $this->workspace('almanara')->id)->where('kind', CatalogKind::Expense)->exists());
        $this->assertFalse(CatalogCategory::query()->withoutWorkspaceScope()
            ->where('workspace_id', $this->workspace('ibdaa')->id)->exists());
    }

    public function test_the_network_has_a_main_router_with_data_and_a_probe_router_whose_key_is_derived(): void
    {
        $this->seedDemo();
        $workspace = $this->workspace('almanara');
        $routers = Router::query()->withoutWorkspaceScope()->where('workspace_id', $workspace->id)->orderBy('created_at')->get();

        $this->assertSame(['Main router', 'Sync probe router'], $routers->pluck('name')->all());
        $this->assertSame($routers[1]->id, Router::findByKey(DemoMarker::routerKeyFor(self::PASSWORD))?->id);
        $this->assertNull(Router::findByKey(DemoMarker::routerKeyFor('another password')));

        $main = $routers[0];
        $this->assertNotNull($main->last_seen_at);
        $this->assertSame('1.0.0', $main->script_version);
        $this->assertGreaterThan(0, $main->commands()->withoutGlobalScopes()->count(), 'provisioned logins were queued');
        $this->assertSame(2, HotspotAccount::query()->withoutWorkspaceScope()->where('workspace_id', $workspace->id)->count());
        $this->assertGreaterThan(0, HotspotSession::query()->withoutGlobalScopes()->where('router_id', $main->id)->count());
    }

    public function test_the_probe_router_accepts_a_text_plain_sync_and_a_wrong_key_is_refused(): void
    {
        $this->seedDemo();
        $code = (string) $this->workspace('almanara')->code;
        $body = 'H|probe-test-1|9|probetest1boot|'.now('UTC')->format('Y-m-d H:i:s')."|full|probetestnonce|1.1.0\n";
        $post = fn (string $key) => $this->call('POST', "/api/v1/hotspot/{$code}/sync", [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
            'HTTP_X-API-Key' => $key,
            'HTTP_ACCEPT' => 'text/plain',
        ], $body);

        $post(DemoMarker::routerKeyFor(self::PASSWORD))->assertOk()->assertSee('CWS-SYNC probetestnonce');
        $post(str_repeat('0', 64))->assertUnauthorized();
    }

    public function test_stations_and_member_profiles_cover_complete_and_incomplete_cases(): void
    {
        $this->seedDemo();
        $workspace = $this->workspace('almanara');
        $stations = Seat::query()->where('workspace_id', $workspace->id)->get();

        $this->assertSame(8, $stations->count());
        $this->assertGreaterThan(0, $stations->where('is_private', true)->count());
        $this->assertGreaterThan(1, $stations->pluck('zone')->filter()->unique()->count());
        $this->assertSame(2, $stations->pluck('branch_id')->filter()->unique()->count());

        $complete = User::query()->where('email', 'freelancer1@demo.taqat.test')->firstOrFail();
        $incomplete = User::query()->where('email', 'freelancer2@demo.taqat.test')->firstOrFail();
        $this->assertNotNull($complete->gender);
        $this->assertNull($incomplete->gender);
    }

    public function test_finance_v2_data_covers_every_document_state(): void
    {
        $this->seedDemo();
        $workspaceId = $this->workspace('almanara')->id;

        $docStatuses = Invoice::query()->withoutGlobalScopes()->where('workspace_id', $workspaceId)->pluck('doc_status')->map->value->unique()->all();
        foreach ([DocStatus::Draft, DocStatus::Issued, DocStatus::Void] as $status) {
            $this->assertContains($status->value, $docStatuses);
        }

        $plans = Plan::query()->withoutWorkspaceScope()->where('workspace_id', $workspaceId)->pluck('pricing_method')->map->value->all();
        $this->assertEqualsCanonicalizing(PricingMethod::values(), $plans);

        $this->assertSame(3, CatalogItem::query()->withoutWorkspaceScope()->where('workspace_id', $workspaceId)->count());
        $this->assertTrue(UnbilledCharge::query()->withoutWorkspaceScope()->where('workspace_id', $workspaceId)->where('status', UnbilledChargeStatus::Open)->exists());
        $this->assertTrue(UnbilledCharge::query()->withoutWorkspaceScope()->where('workspace_id', $workspaceId)->where('status', UnbilledChargeStatus::Voided)->exists());
        $this->assertTrue(Expense::query()->where('workspace_id', $workspaceId)->whereNotNull('voided_at')->exists());
    }

    public function test_receipts_include_a_partial_allocation_and_an_uncollected_cheque(): void
    {
        $this->seedDemo();
        $receipts = Receipt::query()->withoutGlobalScopes()
            ->where('workspace_id', $this->workspace('almanara')->id)->with('allocations')->get();

        $cash = $receipts->firstWhere('cheque_status', null);
        $cheque = $receipts->firstWhere('cheque_status', ChequeStatus::Received);

        $this->assertEqualsWithDelta(30.0, (float) $cash->allocations->sum('amount'), 0.001);
        $this->assertEqualsWithDelta(60.0, (float) $cash->amount, 0.001);
        $this->assertNotNull($cheque);
        $this->assertNull($cheque->collected_at);
    }

    public function test_hour_packages_come_with_a_requested_purchase(): void
    {
        $this->seedDemo();

        $this->assertSame(2, HourPackage::query()->where('code', 'like', 'DEMO-%')->count());
        $this->assertSame(1, HourPackagePurchase::query()->where('status', HourPurchaseStatus::Requested)->count());
    }

    public function test_fresh_removes_the_new_data_cleanly_and_keeps_other_hour_packages(): void
    {
        $this->seedDemo();
        HourPackage::query()->create(['code' => 'REAL-10', 'name_ar' => 'حقيقية', 'name_en' => 'Real', 'hours' => 10, 'price' => 10, 'currency' => 'USD']);
        $counts = $this->counts();

        $this->artisan('demo:seed --fresh')->assertSuccessful();
        $this->artisan('demo:seed --fresh')->assertSuccessful();

        $this->assertSame($counts, $this->counts());
        $this->assertTrue(HourPackage::query()->where('code', 'REAL-10')->exists());
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return [
            'routers' => Router::query()->withoutWorkspaceScope()->count(),
            'accounts' => HotspotAccount::query()->withoutWorkspaceScope()->count(),
            'stations' => Seat::query()->count(),
            'items' => CatalogItem::query()->withoutWorkspaceScope()->count(),
            'plans' => Plan::query()->withoutWorkspaceScope()->count(),
            'invoices' => Invoice::query()->withoutGlobalScopes()->count(),
            'receipts' => Receipt::query()->withoutGlobalScopes()->count(),
            'charges' => UnbilledCharge::query()->withoutWorkspaceScope()->count(),
            'packages' => HourPackage::query()->count(),
            'purchases' => HourPackagePurchase::query()->count(),
        ];
    }
}
