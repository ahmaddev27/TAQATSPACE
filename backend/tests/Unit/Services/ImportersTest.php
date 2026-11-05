<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\CatalogCategory;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Finance\Import\CatalogItemImporter;
use App\Services\Finance\Import\WithdrawalImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportersTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Workspace::factory()->create();
        CatalogCategory::factory()->create(['workspace_id' => $this->workspace->id, 'code' => 'DRINKS']);
    }

    /**
     * @param  array<string, string>  $override
     * @return array{row: int, values: array<string, string>}
     */
    private function itemRow(int $row, array $override = []): array
    {
        return ['row' => $row, 'values' => [...[
            'code' => 'A-1', 'category_code' => 'DRINKS', 'name_ar' => 'ا', 'name_en' => 'A', 'unit' => 'piece', 'price' => '1.5',
        ], ...$override]];
    }

    /** @return list<string> */
    private function columnsOf(array $errors): array
    {
        return array_map(static fn (array $error): string => $error['row'].':'.$error['column'], $errors);
    }

    public function test_catalog_rows_accept_edge_values(): void
    {
        $rows = [
            $this->itemRow(2, ['code' => 'a', 'price' => '0']),
            $this->itemRow(3, ['code' => str_repeat('x', 50), 'price' => '9999999999.99', 'unit' => 'GB']),
        ];

        $this->assertSame([], app(CatalogItemImporter::class)->validate($this->workspace, $rows));
    }

    public function test_catalog_rows_report_each_bad_cell(): void
    {
        $rows = [
            $this->itemRow(2, ['code' => '']),
            $this->itemRow(3, ['code' => str_repeat('x', 51)]),
            $this->itemRow(4, ['code' => '-bad']),
            $this->itemRow(5, ['code' => 'B', 'category_code' => 'nope']),
            $this->itemRow(6, ['code' => 'C', 'price' => '1.234']),
            $this->itemRow(7, ['code' => 'D', 'price' => '1e3']),
            $this->itemRow(8, ['code' => 'E', 'name_en' => '']),
            $this->itemRow(9, ['code' => 'F', 'unit' => 'box']),
        ];

        $errors = app(CatalogItemImporter::class)->validate($this->workspace, $rows);

        $this->assertSame(['2:code', '3:code', '4:code', '5:category_code', '6:price', '7:price', '8:name_en', '9:unit'], $this->columnsOf($errors));
    }

    public function test_an_archived_category_cannot_receive_items(): void
    {
        CatalogCategory::factory()->create(['workspace_id' => $this->workspace->id, 'code' => 'OLD', 'archived_at' => now()]);

        $errors = app(CatalogItemImporter::class)->validate($this->workspace, [$this->itemRow(2, ['category_code' => 'OLD'])]);

        $this->assertSame(['2:category_code'], $this->columnsOf($errors));
    }

    /**
     * @param  array<string, string>  $override
     * @return array{row: int, values: array<string, string>}
     */
    private function withdrawalRow(int $row, string $email, array $override = []): array
    {
        return ['row' => $row, 'values' => [...[
            'source_ref' => 'R'.$row, 'customer_email' => $email, 'description' => 'x', 'qty' => '1', 'unit_price' => '2', 'occurred_at' => '',
        ], ...$override]];
    }

    public function test_withdrawal_rows_validate_amounts_dates_and_customers(): void
    {
        $member = User::factory()->create();
        Subscription::factory()->create(['member_id' => $member->id, 'workspace_id' => $this->workspace->id]);
        $stranger = User::factory()->create();
        $importer = app(WithdrawalImporter::class);

        $rows = [
            $this->withdrawalRow(2, $member->email, ['occurred_at' => '2026-02-30']),
            $this->withdrawalRow(3, $member->email, ['qty' => '0']),
            $this->withdrawalRow(4, $stranger->email),
            $this->withdrawalRow(5, 'nobody@example.test'),
            $this->withdrawalRow(6, $member->email, ['occurred_at' => '2026-03-01 10:30']),
            $this->withdrawalRow(7, $member->email, ['occurred_at' => '46000']),
            $this->withdrawalRow(8, $member->email, ['source_ref' => '']),
        ];

        $this->assertSame(['2:occurred_at', '3:qty', '4:customer_email', '5:customer_email', '8:source_ref'], $this->columnsOf($importer->validate($this->workspace, $rows)));
    }
}
