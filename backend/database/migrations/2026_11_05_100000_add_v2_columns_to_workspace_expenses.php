<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Expenses v2: catalog category, payment method, reference and a soft void.
 * Existing expenses are linked to the catalog category that matches their
 * legacy `category` (code `EXP-<VALUE>`), creating it where the workspace does
 * not have it yet, so nothing is left without a category once Finance v2 is on.
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: string}> legacy value => [Arabic, English] */
    private const NAMES = [
        'rent' => ['الإيجار', 'Rent'],
        'utilities' => ['المرافق', 'Utilities'],
        'salaries' => ['الرواتب', 'Salaries'],
        'maintenance' => ['الصيانة', 'Maintenance'],
        'supplies' => ['المستلزمات', 'Supplies'],
        'marketing' => ['التسويق', 'Marketing'],
        'other' => ['أخرى', 'Other'],
    ];

    public function up(): void
    {
        Schema::table('workspace_expenses', function (Blueprint $table): void {
            $table->foreignUuid('catalog_category_id')->nullable()->after('category')
                ->constrained('catalog_categories')->nullOnDelete();
            $table->string('method', 20)->nullable()->after('amount');
            $table->string('reference', 100)->nullable()->after('method');
            $table->dateTime('voided_at')->nullable();
            $table->string('void_reason', 500)->nullable();

            $table->index(['workspace_id', 'voided_at']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('workspace_expenses', function (Blueprint $table): void {
            $table->dropIndex(['workspace_id', 'voided_at']);
            $table->dropConstrainedForeignId('catalog_category_id');
            $table->dropColumn(['method', 'reference', 'voided_at', 'void_reason']);
        });
    }

    private function backfill(): void
    {
        $this->createMissingCategories();

        foreach (array_keys(self::NAMES) as $value) {
            DB::update(
                'UPDATE workspace_expenses SET catalog_category_id = ('
                .'SELECT catalog_categories.id FROM catalog_categories '
                .'WHERE catalog_categories.workspace_id = workspace_expenses.workspace_id '
                .'AND catalog_categories.code = ?) WHERE category = ?',
                ['EXP-'.Str::upper($value), $value],
            );
        }
    }

    private function createMissingCategories(): void
    {
        $used = DB::table('workspace_expenses')->select('workspace_id', 'category')->distinct()->get();
        $existing = DB::table('catalog_categories')
            ->whereIn('workspace_id', $used->pluck('workspace_id')->unique()->all())
            ->get(['workspace_id', 'code'])
            ->mapWithKeys(fn ($row) => [$row->workspace_id.'|'.$row->code => true]);
        $sort = DB::table('catalog_categories')->selectRaw('workspace_id, MAX(sort_order) as top')
            ->groupBy('workspace_id')->pluck('top', 'workspace_id')->all();
        $now = now();
        $rows = [];

        foreach ($used as $pair) {
            $code = 'EXP-'.Str::upper($pair->category);

            if (! isset(self::NAMES[$pair->category]) || isset($existing[$pair->workspace_id.'|'.$code])) {
                continue;
            }

            $sort[$pair->workspace_id] = ($sort[$pair->workspace_id] ?? -1) + 1;
            $rows[] = [
                'id' => (string) Str::uuid(),
                'workspace_id' => $pair->workspace_id,
                'kind' => 'expense',
                'code' => $code,
                'name_ar' => self::NAMES[$pair->category][0],
                'name_en' => self::NAMES[$pair->category][1],
                'sort_order' => $sort[$pair->workspace_id],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('catalog_categories')->insert($chunk);
        }
    }
};
