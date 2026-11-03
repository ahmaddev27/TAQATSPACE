<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\CatalogItem;
use App\Models\PosProduct;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

class PosCatalogLinkTest extends CatalogTestCase
{
    public function test_backfill_gives_every_pos_product_an_item_with_matching_price_and_category(): void
    {
        [, $workspace] = $this->owningWorkspace();
        [, $other] = $this->owningWorkspace();
        $a = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Latte', 'category' => 'Drinks', 'price' => 6.5, 'sku' => 'LAT-1']);
        $b = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Tea', 'category' => ' drinks ', 'price' => 3, 'sku' => 'LAT-1']);
        $c = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Pen', 'category' => null, 'price' => 1]);
        $d = PosProduct::factory()->create(['workspace_id' => $other->id, 'name' => 'Latte', 'category' => 'Drinks', 'price' => 8, 'sku' => 'LAT-1']);
        DB::table('pos_products')->update(['catalog_item_id' => null]);

        $this->runBackfillMigration();

        foreach ([$a, $b, $c, $d] as $product) {
            $product->refresh();
            $item = DB::table('catalog_items')->find($product->catalog_item_id);
            $category = DB::table('catalog_categories')->find($item->catalog_category_id);
            $this->assertSame($product->workspace_id, $item->workspace_id);
            $this->assertEquals($product->price, $item->price);
            $this->assertSame('sales', $category->kind);
        }

        $itemOf = fn (PosProduct $product) => DB::table('catalog_items')->find($product->fresh()->catalog_item_id);
        $this->assertSame($itemOf($a)->catalog_category_id, $itemOf($b)->catalog_category_id);
        $this->assertSame('LAT-1', $itemOf($a)->code);
        $this->assertSame('LAT-1-2', $itemOf($b)->code);
        $this->assertSame('LAT-1', $itemOf($d)->code);
        $this->assertSame('General', DB::table('catalog_categories')->find($itemOf($c)->catalog_category_id)->name_en);
    }

    public function test_pos_product_linked_to_an_item_takes_name_and_price_from_it(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $item = CatalogItem::factory()->create(['workspace_id' => $workspace->id, 'name_ar' => 'قهوة', 'price' => 4]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/pos/products', ['name' => 'ignored', 'price' => 99, 'catalog_item_id' => $item->id])
            ->assertCreated()
            ->assertJsonPath('data.name', 'قهوة')
            ->assertJsonPath('data.price', '4.00')
            ->assertJsonPath('data.catalog_item_id', $item->id);
    }

    public function test_pos_product_cannot_link_to_an_item_of_another_workspace(): void
    {
        [$owner] = $this->owningWorkspace();
        $foreign = CatalogItem::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/pos/products', ['name' => 'x', 'price' => 1, 'catalog_item_id' => $foreign->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('catalog_item_id');
    }

    public function test_pos_product_keeps_working_after_its_item_is_archived(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $item = CatalogItem::factory()->archived()->create(['workspace_id' => $workspace->id, 'price' => 4]);
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'catalog_item_id' => $item->id]);
        Sanctum::actingAs($owner);

        $this->putJson("/api/pos/products/{$product->id}", ['is_active' => false])->assertOk();
    }

    private function runBackfillMigration(): void
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_07_12_100001_add_catalog_item_id_to_pos_products.php');
        $method = new \ReflectionMethod($migration, 'backfill');
        $products = DB::table('pos_products')->orderBy('id')->get()->all();
        $method->invoke($migration, $products);
    }
}
