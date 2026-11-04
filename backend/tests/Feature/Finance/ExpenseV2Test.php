<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\AuditLog;
use App\Models\CatalogCategory;
use App\Models\Expense;
use App\Models\User;
use App\Models\Workspace;
use App\Services\OwnerReportsService;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\EmulatesMysqlDateFormat;

class ExpenseV2Test extends CatalogTestCase
{
    use EmulatesMysqlDateFormat;

    private const URL = '/api/workspace/expenses';

    protected function setUp(): void
    {
        parent::setUp();

        $this->emulateMysqlDateFormat();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $override = []): array
    {
        return [...[
            'title' => 'Office rent',
            'amount' => '120.50',
            'spent_on' => now()->toDateString(),
        ], ...$override];
    }

    private function expenseCategory(Workspace $workspace, string $code = 'EXP-RENT'): CatalogCategory
    {
        return CatalogCategory::factory()->expense()->create(['workspace_id' => $workspace->id, 'code' => $code]);
    }

    public function test_expense_is_created_with_catalog_category_method_and_reference(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $category = $this->expenseCategory($workspace);
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload([
            'catalog_category_id' => $category->id,
            'method' => 'transfer',
            'reference' => 'TRX-991',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.catalog_category_id', $category->id)
            ->assertJsonPath('data.method', 'transfer')
            ->assertJsonPath('data.reference', 'TRX-991')
            ->assertJsonPath('data.category', 'rent');

        $this->assertTrue(AuditLog::query()->where('action', 'finance.expense.created')->exists());
    }

    public function test_unknown_catalog_code_falls_back_to_the_other_legacy_category(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $category = $this->expenseCategory($workspace, 'CLEANING');
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload(['catalog_category_id' => $category->id]))
            ->assertCreated()
            ->assertJsonPath('data.category', 'other');
    }

    public function test_category_from_another_workspace_is_rejected(): void
    {
        [$owner] = $this->owningWorkspace();
        $foreign = $this->expenseCategory(Workspace::factory()->create());
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload(['catalog_category_id' => $foreign->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('catalog_category_id');
    }

    public function test_sales_and_archived_categories_are_rejected(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $sales = CatalogCategory::factory()->create(['workspace_id' => $workspace->id]);
        $archived = CatalogCategory::factory()->expense()->archived()->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload(['catalog_category_id' => $sales->id]))->assertUnprocessable();
        $this->postJson(self::URL, $this->payload(['catalog_category_id' => $archived->id]))->assertUnprocessable();
    }

    public function test_invalid_method_and_amount_precision_are_rejected(): void
    {
        [$owner] = $this->owningWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload(['category' => 'rent', 'method' => 'bitcoin']))
            ->assertJsonValidationErrors('method');
        $this->postJson(self::URL, $this->payload(['category' => 'rent', 'amount' => '10.555']))
            ->assertJsonValidationErrors('amount');
    }

    public function test_legacy_request_with_category_only_works_when_the_flag_is_off(): void
    {
        [$owner] = $this->owningWorkspace(financeV2: false);
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload(['category' => 'rent']))
            ->assertCreated()
            ->assertJsonPath('data.category', 'rent')
            ->assertJsonPath('data.method', null);
    }

    public function test_a_category_or_a_catalog_category_is_required(): void
    {
        [$owner] = $this->owningWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload())->assertJsonValidationErrors('category');
    }

    public function test_void_requires_a_reason_and_is_audited(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $expense = Expense::factory()->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($owner);

        $this->postJson(self::URL."/{$expense->id}/void", [])->assertJsonValidationErrors('reason');

        $this->postJson(self::URL."/{$expense->id}/void", ['reason' => 'Entered twice'])
            ->assertOk()
            ->assertJsonPath('data.void_reason', 'Entered twice');

        $this->assertNotNull($expense->fresh()->voided_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'finance.expense.voided', 'reason' => 'Entered twice']);
    }

    public function test_voiding_twice_or_editing_a_voided_expense_is_rejected(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $expense = Expense::factory()->create(['workspace_id' => $workspace->id, 'voided_at' => now()]);
        Sanctum::actingAs($owner);

        $this->postJson(self::URL."/{$expense->id}/void", ['reason' => 'again'])->assertUnprocessable();
        $this->putJson(self::URL."/{$expense->id}", ['title' => 'Edited'])->assertUnprocessable();
    }

    public function test_cannot_void_another_workspaces_expense(): void
    {
        [$owner] = $this->owningWorkspace();
        $foreign = Expense::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson(self::URL."/{$foreign->id}/void", ['reason' => 'x'])->assertForbidden();
        $this->assertNull($foreign->fresh()->voided_at);
    }

    public function test_voided_expenses_leave_the_list_and_the_summary(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        Expense::factory()->create(['workspace_id' => $workspace->id, 'amount' => '50.00', 'category' => 'rent', 'spent_on' => now()]);
        Expense::factory()->create(['workspace_id' => $workspace->id, 'amount' => '999.00', 'category' => 'rent', 'spent_on' => now(), 'voided_at' => now()]);
        Sanctum::actingAs($owner);

        $response = $this->getJson(self::URL)->assertOk()->assertJsonPath('data.meta.total', 1);

        $this->assertEquals(50, $response->json('data.summary.total'));
        $this->assertEquals(50, $response->json('data.summary.this_month'));
        $this->assertEquals(50, $response->json('data.summary.by_category.rent'));
        $this->assertEquals(50, collect($response->json('data.summary.by_month'))->sum('total'));

        $this->getJson(self::URL.'?include_voided=1')->assertJsonPath('data.meta.total', 2);
    }

    public function test_voided_expenses_are_excluded_from_the_profit_and_loss(): void
    {
        $workspace = Workspace::factory()->create();
        Expense::factory()->create(['workspace_id' => $workspace->id, 'amount' => '40.00', 'spent_on' => now()]);
        Expense::factory()->create(['workspace_id' => $workspace->id, 'amount' => '500.00', 'spent_on' => now(), 'voided_at' => now()]);

        $current = collect(app(OwnerReportsService::class)->build($workspace)['profit_loss'])->last();

        $this->assertEquals(40, $current['expenses']);
    }

    public function test_delete_voids_with_a_reason_when_finance_v2_is_on(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $expense = Expense::factory()->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($owner);

        $this->deleteJson(self::URL."/{$expense->id}")->assertJsonValidationErrors('reason');
        $this->deleteJson(self::URL."/{$expense->id}", ['reason' => 'Mistake'])->assertOk();

        $this->assertNotNull($expense->fresh()->voided_at);
    }

    public function test_delete_still_removes_the_row_when_finance_v2_is_off(): void
    {
        [$owner, $workspace] = $this->owningWorkspace(financeV2: false);
        $expense = Expense::factory()->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($owner);

        $this->deleteJson(self::URL."/{$expense->id}")->assertOk();

        $this->assertDatabaseMissing('workspace_expenses', ['id' => $expense->id]);
    }

    public function test_non_owner_cannot_void(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $expense = Expense::factory()->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($this->cashierFor($workspace));

        $this->postJson(self::URL."/{$expense->id}/void", ['reason' => 'x'])->assertForbidden();
    }
}
