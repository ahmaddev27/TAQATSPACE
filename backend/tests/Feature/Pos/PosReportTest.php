<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

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
            'source' => 'cashier', 'status' => 'pending',
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

    private function paidOrder(Workspace $workspace, float $total, string $number): void
    {
        PosOrder::query()->create([
            'workspace_id' => $workspace->id,
            'order_number' => $number,
            'source' => 'cashier',
            'status' => 'paid',
            'subtotal' => $total,
            'discount' => 0,
            'total' => $total,
            'paid_at' => now(),
        ]);
    }
}
