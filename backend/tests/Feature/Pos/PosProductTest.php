<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Enums\PosPermission;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Models\PosProduct;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PosPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PosPermissionSeeder::class);
    }

    public function test_owner_creates_a_product_and_opening_stock_is_journalled(): void
    {
        [$owner] = $this->owningWorkspace();
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/pos/products', [
            'name' => 'قهوة',
            'price' => 5,
            'track_stock' => true,
            'stock_qty' => 20,
        ]);

        $response->assertCreated()->assertJsonPath('data.stock_qty', 20);
        $product = PosProduct::query()->firstOrFail();
        $this->assertDatabaseHas('pos_stock_movements', [
            'pos_product_id' => $product->id,
            'type' => StockMovementType::Restock->value,
            'qty_change' => 20,
            'stock_after' => 20,
        ]);
    }

    public function test_restock_and_adjustment_update_stock_and_journal(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'stock_qty' => 10]);
        Sanctum::actingAs($owner);

        // Restock ADDS the amount: 10 + 5 = 15.
        $this->postJson("/api/pos/products/{$product->id}/stock", [
            'type' => StockMovementType::Restock->value,
            'qty' => 5,
        ])->assertOk()->assertJsonPath('data.stock_qty', 15);

        // Adjustment SETS the corrected absolute count (not a delta): -> 12.
        $this->postJson("/api/pos/products/{$product->id}/stock", [
            'type' => StockMovementType::Adjustment->value,
            'qty' => 12,
            'note' => 'جرد',
        ])->assertOk()->assertJsonPath('data.stock_qty', 12);

        $this->assertSame(2, $product->stockMovements()->count());
        // The adjustment journalled the delta (12 - 15 = -3).
        $this->assertDatabaseHas('pos_stock_movements', [
            'pos_product_id' => $product->id,
            'type' => StockMovementType::Adjustment->value,
            'qty_change' => -3,
            'stock_after' => 12,
        ]);
    }

    public function test_adjustment_to_same_count_is_rejected_as_no_change(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'stock_qty' => 7]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/pos/products/{$product->id}/stock", [
            'type' => StockMovementType::Adjustment->value,
            'qty' => 7,
        ])->assertStatus(422);

        $this->assertSame(7, $product->fresh()->stock_qty);
    }

    public function test_cashier_needs_manage_permission_to_create_products(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $cashier = $this->cashierFor($workspace); // sell-only by default

        Sanctum::actingAs($cashier);
        $this->postJson('/api/pos/products', ['name' => 'شاي', 'price' => 3])->assertStatus(403);

        $cashier->givePermissionTo(PosPermission::ManageProducts->value);
        Sanctum::actingAs($cashier->fresh());
        $this->postJson('/api/pos/products', ['name' => 'شاي', 'price' => 3])->assertCreated();
    }

    public function test_products_are_scoped_and_foreign_products_are_not_mutable(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        PosProduct::factory()->count(2)->create(['workspace_id' => $workspace->id]);
        $foreign = PosProduct::factory()->create(); // another workspace

        Sanctum::actingAs($owner);
        $this->getJson('/api/pos/products')->assertOk()->assertJsonCount(2, 'data.products');
        $this->putJson("/api/pos/products/{$foreign->id}", ['price' => 99])->assertNotFound();
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

    private function cashierFor(Workspace $workspace): User
    {
        $cashier = User::factory()->create([
            'role' => UserRole::Cashier->value,
            'workspace_id' => $workspace->id,
        ]);
        $cashier->assignRole(UserRole::Cashier->value);
        $cashier->syncPermissions(PosPermission::defaultsForCashier());

        return $cashier->fresh();
    }
}
