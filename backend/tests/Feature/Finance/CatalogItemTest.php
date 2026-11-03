<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\PosPermission;
use App\Models\AuditLog;
use App\Models\CatalogCategory;
use App\Models\CatalogItem;
use App\Models\PosProduct;
use Laravel\Sanctum\Sanctum;

class CatalogItemTest extends CatalogTestCase
{
    private const URL = '/api/workspace/finance/catalog/items';

    /**
     * @return array<string, mixed>
     */
    private function payload(string $categoryId, array $override = []): array
    {
        return [...[
            'catalog_category_id' => $categoryId,
            'code' => 'COFFEE',
            'name_ar' => 'قهوة',
            'name_en' => 'Coffee',
            'unit' => 'piece',
            'price' => 7.5,
        ], ...$override];
    }

    public function test_owner_creates_an_item_and_it_is_audited(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $category = CatalogCategory::factory()->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload($category->id))
            ->assertCreated()
            ->assertJsonPath('data.code', 'COFFEE')
            ->assertJsonPath('data.price', '7.50')
            ->assertJsonPath('data.category.id', $category->id);

        $this->assertTrue(AuditLog::query()->where('action', 'finance.catalog.item.created')->exists());
    }

    public function test_duplicate_code_is_rejected_in_the_same_workspace_but_allowed_in_another(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $category = CatalogCategory::factory()->create(['workspace_id' => $workspace->id]);
        CatalogItem::factory()->create(['workspace_id' => $workspace->id, 'catalog_category_id' => $category->id, 'code' => 'COFFEE']);
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload($category->id))->assertUnprocessable()->assertJsonValidationErrors('code');

        [$otherOwner, $otherWorkspace] = $this->owningWorkspace();
        $otherCategory = CatalogCategory::factory()->create(['workspace_id' => $otherWorkspace->id]);
        Sanctum::actingAs($otherOwner);
        $this->postJson(self::URL, $this->payload($otherCategory->id))->assertCreated();
    }

    public function test_validation_rejects_bad_unit_negative_price_and_foreign_or_archived_category(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $foreign = CatalogCategory::factory()->create();
        $archived = CatalogCategory::factory()->archived()->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload($foreign->id))->assertJsonValidationErrors('catalog_category_id');
        $this->postJson(self::URL, $this->payload($archived->id))->assertJsonValidationErrors('catalog_category_id');
        $this->postJson(self::URL, $this->payload($archived->id, ['unit' => 'year', 'price' => -1]))
            ->assertJsonValidationErrors(['unit', 'price']);
    }

    public function test_code_cannot_be_changed_after_creation(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $item = CatalogItem::factory()->create(['workspace_id' => $workspace->id, 'code' => 'KEEP']);
        Sanctum::actingAs($owner);

        $this->putJson(self::URL."/{$item->id}", ['code' => 'NEW'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->putJson(self::URL."/{$item->id}", ['price' => 12])->assertOk()->assertJsonPath('data.price', '12.00');

        $this->assertSame('KEEP', $item->fresh()->code);
        $this->assertTrue(AuditLog::query()->where('action', 'finance.catalog.item.updated')->exists());
    }

    public function test_updating_an_item_mirrors_name_and_price_to_linked_pos_products(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $item = CatalogItem::factory()->create(['workspace_id' => $workspace->id, 'price' => 5]);
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'catalog_item_id' => $item->id, 'price' => 5]);
        Sanctum::actingAs($owner);

        $this->putJson(self::URL."/{$item->id}", ['price' => 9, 'name_ar' => 'اسم جديد'])->assertOk();

        $this->assertSame('9.00', $product->fresh()->price);
        $this->assertSame('اسم جديد', $product->fresh()->name);
    }

    public function test_archiving_hides_the_item_but_keeps_the_row_and_linked_product(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $item = CatalogItem::factory()->create(['workspace_id' => $workspace->id]);
        $product = PosProduct::factory()->create(['workspace_id' => $workspace->id, 'catalog_item_id' => $item->id]);
        Sanctum::actingAs($owner);

        $this->deleteJson(self::URL."/{$item->id}")->assertOk()->assertJsonPath('data.is_archived', true);

        $this->getJson(self::URL)->assertJsonCount(0, 'data.items');
        $this->getJson(self::URL.'?archived=only')->assertJsonCount(1, 'data.items');
        $this->assertDatabaseHas('catalog_items', ['id' => $item->id]);
        $this->assertSame($item->id, $product->fresh()->catalog_item_id);
        $this->assertTrue(AuditLog::query()->where('action', 'finance.catalog.item.archived')->exists());
    }

    public function test_search_and_category_filter(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $drinks = CatalogCategory::factory()->create(['workspace_id' => $workspace->id]);
        $services = CatalogCategory::factory()->expense()->create(['workspace_id' => $workspace->id]);
        CatalogItem::factory()->create(['workspace_id' => $workspace->id, 'catalog_category_id' => $drinks->id, 'code' => 'TEA', 'name_en' => 'Tea']);
        CatalogItem::factory()->create(['workspace_id' => $workspace->id, 'catalog_category_id' => $services->id, 'code' => 'PRINT', 'name_en' => 'Printing']);
        Sanctum::actingAs($owner);

        $this->getJson(self::URL.'?search=tea')->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.code', 'TEA');
        $this->getJson(self::URL."?catalog_category_id={$services->id}")->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.code', 'PRINT');
        $this->getJson(self::URL.'?kind=expense')->assertJsonCount(1, 'data.items');
    }

    public function test_other_workspaces_items_are_invisible_and_immutable(): void
    {
        [$owner] = $this->owningWorkspace();
        $foreign = CatalogItem::factory()->create();
        Sanctum::actingAs($owner);

        $this->getJson(self::URL)->assertJsonCount(0, 'data.items');
        $this->putJson(self::URL."/{$foreign->id}", ['price' => 1])->assertNotFound();
        $this->deleteJson(self::URL."/{$foreign->id}")->assertNotFound();
    }

    public function test_cashier_permissions(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $item = CatalogItem::factory()->create(['workspace_id' => $workspace->id]);

        Sanctum::actingAs($this->cashierFor($workspace));
        $this->getJson(self::URL)->assertOk();
        $this->postJson(self::URL, $this->payload($item->catalog_category_id))->assertForbidden();
        $this->putJson(self::URL."/{$item->id}", ['price' => 2])->assertForbidden();
        $this->deleteJson(self::URL."/{$item->id}")->assertForbidden();

        Sanctum::actingAs($this->cashierFor($workspace, [PosPermission::ManageProducts->value]));
        $this->putJson(self::URL."/{$item->id}", ['price' => 2])->assertOk();
    }

    public function test_flag_off_answers_404_and_pos_still_works(): void
    {
        [$owner, $workspace] = $this->owningWorkspace(financeV2: false);
        PosProduct::factory()->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($owner);

        $this->getJson(self::URL)->assertNotFound();
        $this->postJson(self::URL, [])->assertNotFound();
        $this->getJson('/api/pos/products')->assertOk()->assertJsonCount(1, 'data.products');
    }
}
