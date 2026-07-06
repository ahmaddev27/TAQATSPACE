<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Enums\PosPermission;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Models\PosOrder;
use App\Models\PosProduct;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\PosOrderStatusNotification;
use Database\Seeders\PosPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PosPermissionSeeder::class);
    }

    public function test_cashier_rings_up_and_pays_a_walk_in_order_consuming_stock(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $cashier = $this->cashierFor($workspace, [PosPermission::Sell->value]);
        $product = PosProduct::factory()->create([
            'workspace_id' => $workspace->id, 'price' => 5, 'stock_qty' => 10,
        ]);
        Sanctum::actingAs($cashier);

        $create = $this->postJson('/api/pos/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 2]],
            'customer_name' => 'زبون',
        ]);
        $create->assertCreated()
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.total', '10.00');

        $orderId = $create->json('data.id');
        // Payment is decoupled from fulfillment: it records paid_at + consumes
        // stock, but the fulfillment status stays where it was.
        $this->postJson("/api/pos/orders/{$orderId}/pay", ['method' => 'cash'])
            ->assertOk()
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.paid_at', fn ($v) => $v !== null);

        $this->assertSame(8, $product->fresh()->stock_qty);
        $this->assertDatabaseHas('pos_stock_movements', [
            'pos_product_id' => $product->id,
            'type' => StockMovementType::Sale->value,
            'qty_change' => -2,
        ]);
        $this->assertDatabaseHas('pos_payments', ['pos_order_id' => $orderId, 'amount' => '10.00']);
    }

    public function test_discount_is_applied_to_the_total(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'price' => 10, 'stock_qty' => 5]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/pos/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 2]],
            'discount' => 3,
        ])->assertCreated()
            ->assertJsonPath('data.subtotal', '20.00')
            ->assertJsonPath('data.total', '17.00');
    }

    public function test_cannot_order_more_than_available_stock(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'stock_qty' => 1]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/pos/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 5]],
        ])->assertStatus(422);
    }

    public function test_an_order_cannot_be_paid_twice(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'price' => 4, 'stock_qty' => 10]);
        Sanctum::actingAs($owner);

        $id = $this->postJson('/api/pos/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
        ])->json('data.id');

        $this->postJson("/api/pos/orders/{$id}/pay", ['method' => 'cash'])->assertOk();
        $this->postJson("/api/pos/orders/{$id}/pay", ['method' => 'cash'])->assertStatus(422);
    }

    public function test_cashier_without_sell_permission_cannot_create_orders(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $cashier = $this->cashierFor($workspace, [PosPermission::ViewReports->value]); // no sell
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'stock_qty' => 5]);
        Sanctum::actingAs($cashier);

        $this->postJson('/api/pos/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertStatus(403);
    }

    public function test_advancing_fulfillment_status_notifies_the_member(): void
    {
        Notification::fake();

        [$owner, $workspace] = $this->owningWorkspace();
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'price' => 5, 'stock_qty' => 10]);
        $member = User::factory()->freelancer()->create();
        Sanctum::actingAs($owner);

        $order = $this->orderFor($workspace, $product, $member);

        $this->postJson("/api/pos/orders/{$order->id}/status", ['status' => 'preparing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'preparing');

        $this->postJson("/api/pos/orders/{$order->id}/status", ['status' => 'ready'])
            ->assertOk()
            ->assertJsonPath('data.status', 'ready');

        Notification::assertSentTo(
            $member,
            PosOrderStatusNotification::class,
            fn (PosOrderStatusNotification $n): bool => $n->event === 'preparing',
        );
        Notification::assertSentTo(
            $member,
            PosOrderStatusNotification::class,
            fn (PosOrderStatusNotification $n): bool => $n->event === 'ready',
        );
    }

    public function test_status_transition_rejects_unknown_status(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'stock_qty' => 5]);
        Sanctum::actingAs($owner);

        $order = $this->orderFor($workspace, $product, null);

        $this->postJson("/api/pos/orders/{$order->id}/status", ['status' => 'refunded'])
            ->assertStatus(422);
    }

    public function test_walk_in_order_status_change_notifies_no_one(): void
    {
        Notification::fake();

        [$owner, $workspace] = $this->owningWorkspace();
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'stock_qty' => 5]);
        Sanctum::actingAs($owner);

        $order = $this->orderFor($workspace, $product, null);
        $this->postJson("/api/pos/orders/{$order->id}/status", ['status' => 'preparing'])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_refund_restores_stock_marks_refunded_and_notifies(): void
    {
        Notification::fake();

        [$owner, $workspace] = $this->owningWorkspace();
        $cashier = $this->cashierFor($workspace, [PosPermission::Sell->value, PosPermission::Refund->value]);
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'price' => 5, 'stock_qty' => 10]);
        $member = User::factory()->freelancer()->create();
        Sanctum::actingAs($cashier);

        $order = $this->orderFor($workspace, $product, $member);
        $this->postJson("/api/pos/orders/{$order->id}/pay", ['method' => 'cash'])->assertOk();
        $this->assertSame(8, $product->fresh()->stock_qty);

        $this->postJson("/api/pos/orders/{$order->id}/refund")
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded')
            ->assertJsonPath('data.refunded_at', fn ($v) => $v !== null);

        // Stock is restored and an offsetting (negative) payment is recorded.
        $this->assertSame(10, $product->fresh()->stock_qty);
        $this->assertDatabaseHas('pos_payments', ['pos_order_id' => $order->id, 'amount' => '-10.00']);
        $this->assertDatabaseHas('pos_stock_movements', [
            'pos_product_id' => $product->id,
            'type' => StockMovementType::Restock->value,
            'qty_change' => 2,
        ]);

        Notification::assertSentTo(
            $member,
            PosOrderStatusNotification::class,
            fn (PosOrderStatusNotification $n): bool => $n->event === 'refunded',
        );
    }

    public function test_unpaid_order_cannot_be_refunded(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'stock_qty' => 5]);
        Sanctum::actingAs($owner);

        $order = $this->orderFor($workspace, $product, null);

        $this->postJson("/api/pos/orders/{$order->id}/refund")->assertStatus(422);
    }

    public function test_refund_requires_the_refund_permission(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $cashier = $this->cashierFor($workspace, [PosPermission::Sell->value]); // no refund
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'price' => 5, 'stock_qty' => 10]);
        Sanctum::actingAs($cashier);

        $order = $this->orderFor($workspace, $product, null);
        $this->postJson("/api/pos/orders/{$order->id}/pay", ['method' => 'cash'])->assertOk();

        $this->postJson("/api/pos/orders/{$order->id}/refund")->assertStatus(403);
    }

    public function test_a_paid_order_cannot_be_cancelled(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'price' => 5, 'stock_qty' => 10]);
        Sanctum::actingAs($owner);

        $order = $this->orderFor($workspace, $product, null);
        $this->postJson("/api/pos/orders/{$order->id}/pay", ['method' => 'cash'])->assertOk();

        $this->postJson("/api/pos/orders/{$order->id}/cancel")->assertStatus(422);
    }

    public function test_an_open_unpaid_order_can_be_cancelled(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'stock_qty' => 5]);
        Sanctum::actingAs($owner);

        $order = $this->orderFor($workspace, $product, null);
        $this->postJson("/api/pos/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    /**
     * Ring up a one-line order through the API (as the acting user) and return
     * the fresh model, optionally attributed to a member so lifecycle events
     * notify them.
     */
    private function orderFor(Workspace $workspace, PosProduct $product, ?User $member): PosOrder
    {
        $id = $this->postJson('/api/pos/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 2]],
        ])->json('data.id');

        $order = PosOrder::query()->findOrFail($id);

        if ($member !== null) {
            $order->forceFill(['member_id' => $member->id])->save();
        }

        return $order->refresh();
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function owningWorkspace(): array
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        return [$owner, $workspace];
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
