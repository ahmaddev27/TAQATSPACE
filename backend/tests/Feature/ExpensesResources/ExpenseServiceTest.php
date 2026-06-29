<?php

declare(strict_types=1);

namespace Tests\Feature\ExpensesResources;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\Workspace;
use App\Services\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ExpenseServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The monthly breakdown buckets months with MySQL's DATE_FORMAT, which
        // sqlite lacks — register an equivalent UDF so the summary runs here.
        DB::connection()->getPdo()->sqliteCreateFunction(
            'DATE_FORMAT',
            static function (?string $date, string $format): ?string {
                if ($date === null) {
                    return null;
                }

                $php = str_replace(['%Y', '%m', '%d'], ['Y', 'm', 'd'], $format);

                return date($php, (int) strtotime($date));
            },
            2,
        );
    }

    private function service(): ExpenseService
    {
        return app(ExpenseService::class);
    }

    public function test_create_attaches_expense_to_the_workspace(): void
    {
        $workspace = Workspace::factory()->create();

        $expense = $this->service()->create($workspace, [
            'title' => 'Rent',
            'category' => ExpenseCategory::Rent->value,
            'amount' => 1500,
            'spent_on' => '2026-01-10',
            'notes' => 'Monthly office rent',
        ]);

        $this->assertSame($workspace->id, $expense->workspace_id);
        $this->assertSame('Rent', $expense->title);
        $this->assertSame(ExpenseCategory::Rent, $expense->category);
        $this->assertDatabaseHas('workspace_expenses', [
            'id' => $expense->id,
            'workspace_id' => $workspace->id,
            'title' => 'Rent',
        ]);
    }

    public function test_update_persists_changes_for_owned_expense(): void
    {
        $workspace = Workspace::factory()->create();
        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'title' => 'Old',
            'amount' => 100,
        ]);

        $updated = $this->service()->update($workspace, $expense, [
            'title' => 'New',
            'amount' => 250,
        ]);

        $this->assertSame('New', $updated->title);
        $this->assertSame('250.00', $updated->amount);
        $this->assertDatabaseHas('workspace_expenses', [
            'id' => $expense->id,
            'title' => 'New',
        ]);
    }

    public function test_update_rejects_expense_from_another_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $other = Workspace::factory()->create();
        $foreign = Expense::factory()->create(['workspace_id' => $other->id]);

        $this->expectException(RuntimeException::class);

        $this->service()->update($workspace, $foreign, ['title' => 'Hacked']);
    }

    public function test_delete_removes_owned_expense(): void
    {
        $workspace = Workspace::factory()->create();
        $expense = Expense::factory()->create(['workspace_id' => $workspace->id]);

        $this->service()->delete($workspace, $expense);

        $this->assertDatabaseMissing('workspace_expenses', ['id' => $expense->id]);
    }

    public function test_delete_rejects_expense_from_another_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $other = Workspace::factory()->create();
        $foreign = Expense::factory()->create(['workspace_id' => $other->id]);

        $this->expectException(RuntimeException::class);

        $this->service()->delete($workspace, $foreign);

        $this->assertDatabaseHas('workspace_expenses', ['id' => $foreign->id]);
    }

    public function test_paginate_is_scoped_to_the_workspace_and_orders_newest_first(): void
    {
        $workspace = Workspace::factory()->create();
        $other = Workspace::factory()->create();

        Expense::factory()->create(['workspace_id' => $other->id]);
        $older = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'spent_on' => '2026-01-01',
        ]);
        $newer = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'spent_on' => '2026-03-01',
        ]);

        $page = $this->service()->paginateForWorkspace($workspace, []);

        $this->assertSame(2, $page->total());
        $this->assertSame($newer->id, $page->items()[0]->id);
        $this->assertSame($older->id, $page->items()[1]->id);
    }

    public function test_paginate_clamps_per_page_to_safe_bounds(): void
    {
        $workspace = Workspace::factory()->create();
        Expense::factory()->count(3)->create(['workspace_id' => $workspace->id]);

        $this->assertSame(200, $this->service()->paginateForWorkspace($workspace, ['per_page' => 5000])->perPage());
        $this->assertSame(1, $this->service()->paginateForWorkspace($workspace, ['per_page' => 0])->perPage());
    }

    public function test_filtered_query_applies_category_and_date_range(): void
    {
        $workspace = Workspace::factory()->create();

        $rent = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'category' => ExpenseCategory::Rent->value,
            'spent_on' => '2026-02-15',
        ]);
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'category' => ExpenseCategory::Utilities->value,
            'spent_on' => '2026-02-15',
        ]);
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'category' => ExpenseCategory::Rent->value,
            'spent_on' => '2026-05-15',
        ]);

        $page = $this->service()->paginateForWorkspace($workspace, [
            'category' => ExpenseCategory::Rent->value,
            'from' => '2026-02-01',
            'to' => '2026-02-28',
        ]);

        $this->assertSame(1, $page->total());
        $this->assertSame($rent->id, $page->items()[0]->id);
    }

    public function test_category_filter_all_is_ignored(): void
    {
        $workspace = Workspace::factory()->create();
        Expense::factory()->count(2)->create([
            'workspace_id' => $workspace->id,
            'category' => ExpenseCategory::Rent->value,
        ]);
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'category' => ExpenseCategory::Utilities->value,
        ]);

        $page = $this->service()->paginateForWorkspace($workspace, ['category' => 'all']);

        $this->assertSame(3, $page->total());
    }

    public function test_summary_total_respects_filters(): void
    {
        $workspace = Workspace::factory()->create();
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'category' => ExpenseCategory::Rent->value,
            'amount' => 100,
        ]);
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'category' => ExpenseCategory::Utilities->value,
            'amount' => 50,
        ]);

        $all = $this->service()->summaryForWorkspace($workspace, []);
        $this->assertSame('150', (string) $all['total']);

        $rentOnly = $this->service()->summaryForWorkspace($workspace, [
            'category' => ExpenseCategory::Rent->value,
        ]);
        $this->assertSame('100', (string) $rentOnly['total']);
    }

    public function test_summary_this_month_only_counts_current_calendar_month(): void
    {
        $workspace = Workspace::factory()->create();

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'amount' => 200,
            'spent_on' => Carbon::now()->startOfMonth()->addDays(2)->toDateString(),
        ]);
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'amount' => 999,
            'spent_on' => Carbon::now()->subMonthsNoOverflow(2)->toDateString(),
        ]);

        $summary = $this->service()->summaryForWorkspace($workspace, []);

        $this->assertSame('200', (string) $summary['this_month']);
    }

    public function test_summary_by_category_contains_every_category_with_zero_fill(): void
    {
        $workspace = Workspace::factory()->create();
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'category' => ExpenseCategory::Marketing->value,
            'amount' => 75,
        ]);

        $summary = $this->service()->summaryForWorkspace($workspace, []);

        foreach (ExpenseCategory::cases() as $category) {
            $this->assertArrayHasKey($category->value, $summary['by_category']);
        }
        $this->assertSame('75', (string) $summary['by_category'][ExpenseCategory::Marketing->value]);
        $this->assertSame('0', (string) $summary['by_category'][ExpenseCategory::Rent->value]);
    }

    public function test_summary_by_month_is_a_contiguous_twelve_month_series(): void
    {
        $workspace = Workspace::factory()->create();
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'amount' => 300,
            'spent_on' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $summary = $this->service()->summaryForWorkspace($workspace, []);

        $this->assertCount(12, $summary['by_month']);

        $currentKey = Carbon::now()->format('Y-m');
        $last = $summary['by_month'][11];
        $this->assertSame($currentKey, $last['month']);
        $this->assertSame('300', (string) $last['total']);

        // Earlier buckets default to zero.
        $this->assertSame('0', (string) $summary['by_month'][0]['total']);
    }

    public function test_summary_is_isolated_per_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $other = Workspace::factory()->create();

        Expense::factory()->create(['workspace_id' => $workspace->id, 'amount' => 10]);
        Expense::factory()->create(['workspace_id' => $other->id, 'amount' => 9999]);

        $summary = $this->service()->summaryForWorkspace($workspace, []);

        $this->assertSame('10', (string) $summary['total']);
    }

    public function test_export_query_is_scoped_and_ordered_oldest_first(): void
    {
        $workspace = Workspace::factory()->create();
        $other = Workspace::factory()->create();
        Expense::factory()->create(['workspace_id' => $other->id]);

        $newer = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'spent_on' => '2026-04-01',
        ]);
        $older = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'spent_on' => '2026-01-01',
        ]);

        $rows = $this->service()->exportQueryForWorkspace($workspace, [])->get();

        $this->assertCount(2, $rows);
        $this->assertSame($older->id, $rows[0]->id);
        $this->assertSame($newer->id, $rows[1]->id);
    }
}
