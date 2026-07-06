<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Enums\PosPermission;
use App\Enums\UserRole;
use App\Models\PosOrder;
use App\Models\PosProduct;
use App\Models\User;
use App\Models\Workspace;
use App\Services\OwnerReportsService;
use Database\Seeders\PosPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PosPermissionSeeder::class);
    }

    public function test_paid_pos_orders_count_as_revenue_in_the_owner_pl(): void
    {
        // The P&L groups by month with DATE_FORMAT (MySQL-only, like the existing
        // invoice-revenue query it mirrors); the sqlite test DB can't run it.
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('profit_loss uses DATE_FORMAT (MySQL only).');
        }

        $workspace = Workspace::factory()->create();
        $this->paidOrder($workspace, 20, 'POS-A');

        $pl = app(OwnerReportsService::class)->build($workspace)['profit_loss'];
        $currentMonth = end($pl); // trailing window ends at the current month

        $this->assertSame(now()->format('Y-m'), $currentMonth['month']);
        $this->assertSame('20.00', $currentMonth['revenue']);
    }

    public function test_summary_endpoint_reports_sales_pending_and_low_stock(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->paidOrder($workspace, 15, 'POS-1');
        PosOrder::query()->create([
            'workspace_id' => $workspace->id, 'order_number' => 'POS-2',
            'source' => 'cashier', 'status' => 'new',
            'subtotal' => 5, 'discount' => 0, 'total' => 5,
        ]);
        PosProduct::factory()->create([
            'workspace_id' => $workspace->id, 'track_stock' => true, 'stock_qty' => 2, 'is_active' => true,
        ]);

        Sanctum::actingAs($owner);
        $this->getJson('/api/pos/summary')
            ->assertOk()
            ->assertJsonPath('data.today_sales', '15.00')
            ->assertJsonPath('data.today_orders', 1)
            ->assertJsonPath('data.pending_orders', 1)
            ->assertJsonPath('data.low_stock', 1);
    }

    public function test_reports_endpoint_aggregates_paid_orders_and_refunds(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->paidOrderWithLines($workspace, 'POS-A', 'cash', $owner->id, [
            ['name' => 'Latte', 'qty' => 2, 'line_total' => 20],
        ]);
        $this->paidOrderWithLines($workspace, 'POS-B', 'cash', $owner->id, [
            ['name' => 'Latte', 'qty' => 1, 'line_total' => 10],
            ['name' => 'Muffin', 'qty' => 1, 'line_total' => 5],
        ]);
        $this->paidOrderWithLines($workspace, 'POS-C', 'transfer', $owner->id, [
            ['name' => 'Espresso', 'qty' => 1, 'line_total' => 8],
        ]);
        $this->refundedOrder($workspace, 'POS-R', 12);

        Sanctum::actingAs($owner);
        $res = $this->getJson('/api/pos/reports')->assertOk();

        // Net sales (43 = 20 + 15 + 8) exclude the refunded order.
        $res->assertJsonPath('data.totals.sales', '43.00')
            ->assertJsonPath('data.totals.orders', 3)
            ->assertJsonPath('data.totals.avg', '14.33')
            ->assertJsonPath('data.refunds.count', 1)
            ->assertJsonPath('data.refunds.total', '12.00');

        // Top products are name-grouped: Latte sold 3 across two orders.
        $topProducts = collect($res->json('data.top_products'));
        $latte = $topProducts->firstWhere('name', 'Latte');
        $this->assertNotNull($latte);
        $this->assertSame(3, $latte['qty']);
        $this->assertSame('30.00', $latte['total']);

        // Payment methods sum: cash 35 (20 + 15), transfer 8.
        $byMethod = collect($res->json('data.by_method'))->pluck('total', 'method');
        $this->assertSame('35.00', $byMethod['cash']);
        $this->assertSame('8.00', $byMethod['transfer']);
    }

    public function test_cashier_without_view_reports_permission_is_forbidden(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $cashier = $this->cashierFor($workspace, [PosPermission::Sell->value]); // no view_reports

        Sanctum::actingAs($cashier);
        $this->getJson('/api/pos/reports')->assertStatus(403);
    }

    public function test_cashier_with_view_reports_permission_can_read_reports(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $cashier = $this->cashierFor($workspace, [PosPermission::ViewReports->value]);

        Sanctum::actingAs($cashier);
        $this->getJson('/api/pos/reports')
            ->assertOk()
            ->assertJsonPath('data.totals.orders', 0)
            ->assertJsonPath('data.totals.sales', '0.00');
    }

    private function paidOrder(Workspace $workspace, float $total, string $number): void
    {
        PosOrder::query()->create([
            'workspace_id' => $workspace->id,
            'order_number' => $number,
            'source' => 'cashier',
            'status' => 'completed',
            'subtotal' => $total,
            'discount' => 0,
            'total' => $total,
            'paid_at' => now(),
        ]);
    }

    /**
     * Create a paid, completed order with snapshotted item lines and a single
     * settling payment of the given method.
     *
     * @param  array<int, array{name: string, qty: int, line_total: float|int}>  $lines
     */
    private function paidOrderWithLines(
        Workspace $workspace,
        string $number,
        string $method,
        string $cashierId,
        array $lines,
    ): void {
        $total = array_sum(array_column($lines, 'line_total'));

        $order = PosOrder::query()->create([
            'workspace_id' => $workspace->id,
            'order_number' => $number,
            'source' => 'cashier',
            'status' => 'completed',
            'cashier_id' => $cashierId,
            'subtotal' => $total,
            'discount' => 0,
            'total' => $total,
            'paid_at' => now(),
        ]);

        foreach ($lines as $line) {
            $order->items()->create([
                'name' => $line['name'],
                'unit_price' => (float) $line['line_total'] / max(1, $line['qty']),
                'qty' => $line['qty'],
                'line_total' => $line['line_total'],
            ]);
        }

        $order->payments()->create([
            'amount' => $total,
            'method' => $method,
            'received_by' => $cashierId,
            'paid_at' => now(),
        ]);
    }

    private function refundedOrder(Workspace $workspace, string $number, float $total): void
    {
        PosOrder::query()->create([
            'workspace_id' => $workspace->id,
            'order_number' => $number,
            'source' => 'cashier',
            'status' => 'refunded',
            'subtotal' => $total,
            'discount' => 0,
            'total' => $total,
            'paid_at' => now(),
            'refunded_at' => now(),
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function cashierFor(Workspace $workspace, array $permissions): User
    {
        $cashier = User::factory()->create([
            'role' => UserRole::Cashier->value,
            'workspace_id' => $workspace->id,
        ]);
        $cashier->assignRole(UserRole::Cashier->value);
        $cashier->syncPermissions($permissions);

        return $cashier->fresh();
    }
}
