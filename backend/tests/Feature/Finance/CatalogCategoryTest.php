<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\PosPermission;
use App\Enums\WorkspaceFeature;
use App\Models\AuditLog;
use App\Models\CatalogCategory;
use App\Models\Workspace;
use App\Services\FeatureFlagService;
use Laravel\Sanctum\Sanctum;

class CatalogCategoryTest extends CatalogTestCase
{
    private const URL = '/api/workspace/finance/catalog/categories';

    /**
     * @return array<string, mixed>
     */
    private function payload(array $override = []): array
    {
        return [...['kind' => 'sales', 'code' => 'DRINKS', 'name_ar' => 'مشروبات', 'name_en' => 'Drinks'], ...$override];
    }

    public function test_owner_creates_a_category_and_it_is_audited(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.code', 'DRINKS')
            ->assertJsonPath('data.kind', 'sales');

        $this->assertDatabaseHas('catalog_categories', ['workspace_id' => $workspace->id, 'code' => 'DRINKS']);
        $this->assertTrue(AuditLog::query()->where('action', 'finance.catalog.category.created')->exists());
    }

    public function test_duplicate_code_is_rejected_in_the_same_workspace_but_allowed_in_another(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        CatalogCategory::factory()->create(['workspace_id' => $workspace->id, 'code' => 'DRINKS']);
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload())->assertUnprocessable()->assertJsonValidationErrors('code');

        [$otherOwner] = $this->owningWorkspace();
        Sanctum::actingAs($otherOwner);
        $this->postJson(self::URL, $this->payload())->assertCreated();
    }

    public function test_code_and_kind_cannot_be_changed_after_creation(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $category = CatalogCategory::factory()->create(['workspace_id' => $workspace->id, 'code' => 'KEEP']);
        Sanctum::actingAs($owner);

        $this->putJson(self::URL."/{$category->id}", ['code' => 'NEW'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->putJson(self::URL."/{$category->id}", ['kind' => 'expense'])->assertUnprocessable()->assertJsonValidationErrors('kind');
        $this->putJson(self::URL."/{$category->id}", ['name_en' => 'Renamed'])->assertOk()->assertJsonPath('data.name_en', 'Renamed');

        $this->assertSame('KEEP', $category->fresh()->code);
    }

    public function test_archiving_hides_the_category_from_the_default_list_only(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $keep = CatalogCategory::factory()->create(['workspace_id' => $workspace->id]);
        $gone = CatalogCategory::factory()->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($owner);

        $this->deleteJson(self::URL."/{$gone->id}")->assertOk()->assertJsonPath('data.is_archived', true);

        $this->getJson(self::URL)->assertOk()->assertJsonCount(1, 'data.categories')
            ->assertJsonPath('data.categories.0.id', $keep->id);
        $this->getJson(self::URL.'?archived=include')->assertJsonCount(2, 'data.categories');
        $this->getJson(self::URL.'?archived=only')->assertJsonCount(1, 'data.categories')
            ->assertJsonPath('data.categories.0.id', $gone->id);
        $this->assertDatabaseHas('catalog_categories', ['id' => $gone->id]);
    }

    public function test_reorder_sets_sort_order_from_the_given_sequence(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        [$a, $b, $c] = CatalogCategory::factory()->count(3)->create(['workspace_id' => $workspace->id])->all();
        Sanctum::actingAs($owner);

        $this->postJson(self::URL.'/reorder', ['ids' => [$c->id, $a->id, $b->id]])->assertOk();

        $this->assertSame([0, 1, 2], [$c->fresh()->sort_order, $a->fresh()->sort_order, $b->fresh()->sort_order]);
        $this->getJson(self::URL)->assertJsonPath('data.categories.0.id', $c->id);
    }

    public function test_reorder_rejects_a_category_of_another_workspace(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $mine = CatalogCategory::factory()->create(['workspace_id' => $workspace->id, 'sort_order' => 5]);
        $foreign = CatalogCategory::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson(self::URL.'/reorder', ['ids' => [$mine->id, $foreign->id]])->assertUnprocessable();
        $this->assertSame(5, $mine->fresh()->sort_order);
    }

    public function test_other_workspaces_categories_are_invisible_and_immutable(): void
    {
        [$owner] = $this->owningWorkspace();
        $foreign = CatalogCategory::factory()->create();
        Sanctum::actingAs($owner);

        $this->getJson(self::URL)->assertOk()->assertJsonCount(0, 'data.categories');
        $this->putJson(self::URL."/{$foreign->id}", ['name_en' => 'Hacked'])->assertNotFound();
        $this->deleteJson(self::URL."/{$foreign->id}")->assertNotFound();
    }

    public function test_cashier_permissions(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $category = CatalogCategory::factory()->create(['workspace_id' => $workspace->id]);

        Sanctum::actingAs($this->cashierFor($workspace));
        $this->getJson(self::URL)->assertOk();
        $this->postJson(self::URL, $this->payload())->assertForbidden();
        $this->deleteJson(self::URL."/{$category->id}")->assertForbidden();

        Sanctum::actingAs($this->cashierFor($workspace, [PosPermission::ManageProducts->value]));
        $this->postJson(self::URL, $this->payload())->assertCreated();
    }

    public function test_a_user_without_a_workspace_is_rejected(): void
    {
        Sanctum::actingAs(\App\Models\User::factory()->create());

        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_flag_off_answers_404(): void
    {
        [$owner] = $this->owningWorkspace(financeV2: false);
        Sanctum::actingAs($owner);

        $this->getJson(self::URL)->assertNotFound();
        $this->postJson(self::URL, $this->payload())->assertNotFound();
    }

    public function test_enabling_finance_v2_seeds_the_expense_categories_once(): void
    {
        $workspace = Workspace::factory()->create();
        $flags = app(FeatureFlagService::class);

        $flags->update($workspace, [WorkspaceFeature::FinanceV2->value => true]);
        $flags->update($workspace, [WorkspaceFeature::FinanceV2->value => false]);
        $flags->update($workspace, [WorkspaceFeature::FinanceV2->value => true]);

        $codes = CatalogCategory::query()->withoutWorkspaceScope()->where('workspace_id', $workspace->id)
            ->where('kind', 'expense')->pluck('code')->all();
        $this->assertCount(7, $codes);
        $this->assertContains('EXP-RENT', $codes);
    }
}
