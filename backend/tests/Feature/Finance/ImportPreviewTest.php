<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\CatalogItem;
use App\Models\ImportBatch;
use App\Services\Finance\Import\XlsxReader;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

class ImportPreviewTest extends ImportTestCase
{
    public function test_a_clean_file_previews_without_writing_any_item(): void
    {
        $this->signedInOwner();

        $this->preview($this->xlsx($this->itemRows(3)))
            ->assertCreated()
            ->assertJsonPath('data.status', 'previewed')
            ->assertJsonPath('data.row_count', 3)
            ->assertJsonPath('data.error_count', 0)
            ->assertJsonPath('data.can_commit', true);

        $this->assertSame(0, CatalogItem::query()->withoutWorkspaceScope()->count());
        $this->assertSame(1, ImportBatch::query()->withoutWorkspaceScope()->count());
    }

    public function test_row_and_column_errors_are_reported_and_the_batch_cannot_commit(): void
    {
        $this->signedInOwner();
        $rows = $this->itemRows(3);
        $rows[2][4] = 'bottle';
        $rows[3][5] = '-4';

        $response = $this->preview($this->xlsx($rows))->assertCreated()->assertJsonPath('data.status', 'failed');

        $errors = collect($response->json('data.errors'));
        $this->assertSame(2, $errors->count());
        $this->assertSame([3, 'unit'], [$errors[0]['row'], $errors[0]['column']]);
        $this->assertSame([4, 'price'], [$errors[1]['row'], $errors[1]['column']]);
        $response->assertJsonPath('data.can_commit', false);
    }

    public function test_more_than_2000_rows_is_rejected(): void
    {
        $this->signedInOwner();

        $this->preview($this->xlsx($this->itemRows(2001)))
            ->assertStatus(422)
            ->assertJsonPath('message', __('messages.import_too_many_rows', ['max' => 2000]));

        $this->assertSame(0, ImportBatch::query()->withoutWorkspaceScope()->count());
    }

    public function test_exactly_2000_rows_is_accepted(): void
    {
        $this->signedInOwner();

        $this->preview($this->xlsx($this->itemRows(2000)))->assertCreated()->assertJsonPath('data.row_count', 2000);
    }

    public function test_a_file_over_5mb_is_rejected_before_it_is_opened(): void
    {
        $this->signedInOwner();

        $this->preview(UploadedFile::fake()->create('big.xlsx', 5121))->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_a_non_xlsx_file_is_rejected(): void
    {
        $this->signedInOwner();

        $this->preview(UploadedFile::fake()->createWithContent('data.csv', "a,b\n1,2"))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->preview(UploadedFile::fake()->createWithContent('fake.xlsx', 'not a zip'))
            ->assertStatus(422)
            ->assertJsonPath('message', __('messages.import_not_xlsx'));
    }

    public function test_a_workbook_with_two_sheets_is_rejected(): void
    {
        $this->signedInOwner();

        $this->preview($this->xlsx($this->itemRows(1), sheets: 2))
            ->assertStatus(422)
            ->assertJsonPath('message', __('messages.import_single_sheet_only'));
    }

    public function test_a_file_with_a_doctype_is_rejected_as_a_possible_xxe(): void
    {
        $this->signedInOwner();
        $doctype = '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]>';

        $this->preview($this->xlsx($this->itemRows(1), doctype: $doctype))
            ->assertStatus(422)
            ->assertJsonPath('message', __('messages.import_not_xlsx'));
    }

    public function test_missing_columns_are_named(): void
    {
        $this->signedInOwner();

        $response = $this->preview($this->xlsx([['code', 'name_en'], ['A1', 'Alpha']]))->assertStatus(422);

        $this->assertStringContainsString('category_code', (string) $response->json('message'));
    }

    public function test_an_unknown_type_is_a_validation_error(): void
    {
        $this->signedInOwner();

        $this->preview($this->xlsx($this->itemRows(1)), 'subscribers')->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_a_cashier_needs_the_grant_matching_the_type(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $workspace->refresh();
        Sanctum::actingAs($this->cashierFor($workspace, ['finance_items']));
        \App\Models\CatalogCategory::factory()->create(['workspace_id' => $workspace->id, 'code' => 'DRINKS']);

        $this->preview($this->xlsx($this->itemRows(1)))->assertCreated();
        $this->preview($this->xlsx([self::WITHDRAWAL_HEADERS, ['W1', 'a@b.c', 'x', '1', '1', '']]), 'withdrawals')->assertForbidden();
    }

    public function test_flag_off_answers_404(): void
    {
        [$owner] = $this->owningWorkspace(financeV2: false);
        Sanctum::actingAs($owner);

        $this->preview($this->xlsx($this->itemRows(1)))->assertNotFound();
        $this->getJson('/api/workspace/finance/imports/template/catalog_items')->assertNotFound();
    }

    public function test_templates_download_and_read_back_as_valid_files(): void
    {
        $this->signedInOwner();

        foreach (['catalog_items', 'withdrawals'] as $type) {
            $response = $this->get('/api/workspace/finance/imports/template/'.$type)->assertOk();
            $path = tempnam(sys_get_temp_dir(), 'tpl');
            file_put_contents($path, $response->getContent());

            $parsed = app(XlsxReader::class)->read($path);

            $this->assertNotEmpty($parsed['headers']);
            $this->assertCount(1, $parsed['rows']);
            unlink($path);
        }
    }
}
