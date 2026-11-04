<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Links POS products to the finance catalog and backfills it: every existing
 * product gets one catalog item, filed under a category created from its
 * free-text `category` (or a "General" one when it has none). Codes are
 * generated per workspace and kept unique in memory, since the catalog starts
 * empty.
 */
return new class extends Migration
{
    /** @var array<string, string> "workspace|category label" => category id */
    private array $categoryIds = [];

    /** @var array<string, array<string, true>> workspace id => used codes (categories and items) */
    private array $usedCodes = [];

    /** @var array<string, int> workspace id => last category sort order */
    private array $lastSort = [];

    public function up(): void
    {
        Schema::table('pos_products', function (Blueprint $table): void {
            $table->foreignUuid('catalog_item_id')->nullable()->after('workspace_id')
                ->constrained('catalog_items')->nullOnDelete();
        });

        DB::table('pos_products')->orderBy('id')->chunk(500, fn ($products) => $this->backfill($products->all()));
    }

    public function down(): void
    {
        Schema::table('pos_products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('catalog_item_id');
        });
    }

    /** @param  array<int, object>  $products */
    private function backfill(array $products): void
    {
        $now = now();
        $categories = [];
        $items = [];
        $links = [];

        foreach ($products as $product) {
            $itemId = (string) Str::uuid();
            $items[] = [
                'id' => $itemId,
                'workspace_id' => $product->workspace_id,
                'catalog_category_id' => $this->categoryIdFor($product, $categories, $now),
                'code' => $this->uniqueCode($product->workspace_id, $this->itemBaseCode($product)),
                'name_ar' => $product->name,
                'name_en' => $product->name,
                'unit' => 'piece',
                'price' => $product->price,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $links[$product->id] = $itemId;
        }

        if ($categories !== []) {
            DB::table('catalog_categories')->insert($categories);
        }

        DB::table('catalog_items')->insert($items);
        $this->linkProducts($links);
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories  collects the category rows still to insert
     */
    private function categoryIdFor(object $product, array &$categories, mixed $now): string
    {
        $label = trim((string) $product->category);
        $key = $product->workspace_id.'|'.mb_strtolower($label);

        if (isset($this->categoryIds[$key])) {
            return $this->categoryIds[$key];
        }

        $id = (string) Str::uuid();
        $categories[] = [
            'id' => $id,
            'workspace_id' => $product->workspace_id,
            'kind' => 'sales',
            'code' => $this->uniqueCode($product->workspace_id, $this->categoryBaseCode($label)),
            'name_ar' => $label === '' ? 'عام' : $label,
            'name_en' => $label === '' ? 'General' : $label,
            'sort_order' => $this->lastSort[$product->workspace_id] = ($this->lastSort[$product->workspace_id] ?? -1) + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        return $this->categoryIds[$key] = $id;
    }

    private function categoryBaseCode(string $label): string
    {
        $slug = Str::upper(Str::slug($label));

        return 'POS-'.($slug === '' ? 'GENERAL' : Str::limit($slug, 30, ''));
    }

    private function itemBaseCode(object $product): string
    {
        $sku = Str::upper(Str::slug((string) $product->sku));

        return $sku === '' ? 'POS-ITEM' : Str::limit($sku, 40, '');
    }

    private function uniqueCode(string $workspaceId, string $base): string
    {
        $code = $base;

        for ($n = 2; isset($this->usedCodes[$workspaceId][$code]); $n++) {
            $code = $base.'-'.$n;
        }

        $this->usedCodes[$workspaceId][$code] = true;

        return $code;
    }

    /** @param  array<string, string>  $links  product id => catalog item id */
    private function linkProducts(array $links): void
    {
        $cases = implode(' ', array_fill(0, count($links), 'WHEN ? THEN ?'));
        $placeholders = implode(',', array_fill(0, count($links), '?'));
        $bindings = [];

        foreach ($links as $productId => $itemId) {
            array_push($bindings, $productId, $itemId);
        }

        DB::update(
            "UPDATE pos_products SET catalog_item_id = CASE id {$cases} END WHERE id IN ({$placeholders})",
            array_merge($bindings, array_keys($links)),
        );
    }
};
