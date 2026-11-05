<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\CatalogItem;
use App\Models\ImportBatch;
use App\Models\UnbilledCharge;

class ImportDuplicateTest extends ImportTestCase
{
    public function test_uploading_a_committed_file_again_is_rejected_and_duplicates_nothing(): void
    {
        $this->signedInOwner();
        $rows = $this->itemRows(3);
        $batchId = $this->preview($this->xlsx($rows))->json('data.id');
        $this->postJson('/api/workspace/finance/imports/'.$batchId.'/commit')->assertOk();

        $this->preview($this->xlsx($rows))->assertStatus(409)->assertJsonPath('message', __('messages.import_duplicate_file'));
        $this->postJson('/api/workspace/finance/imports/'.$batchId.'/commit')->assertStatus(409);

        $this->assertSame(3, CatalogItem::query()->withoutWorkspaceScope()->count());
        $this->assertSame(1, ImportBatch::query()->withoutWorkspaceScope()->count());
    }

    public function test_previewing_the_same_file_twice_reuses_one_batch(): void
    {
        $this->signedInOwner();
        $rows = $this->itemRows(2);

        $first = $this->preview($this->xlsx($rows))->json('data.id');
        $second = $this->preview($this->xlsx($rows))->json('data.id');

        $this->assertSame($first, $second);
    }

    public function test_an_existing_code_is_a_row_error_not_an_update(): void
    {
        [, $workspace] = $this->signedInOwner();
        $batchId = $this->preview($this->xlsx($this->itemRows(1)))->json('data.id');
        $this->postJson('/api/workspace/finance/imports/'.$batchId.'/commit')->assertOk();

        $rows = $this->itemRows(2);
        $rows[1][3] = 'Renamed';
        $response = $this->preview($this->xlsx($rows))->assertCreated()->assertJsonPath('data.status', 'failed');

        $this->assertSame([2, 'code'], [$response->json('data.errors.0.row'), $response->json('data.errors.0.column')]);
        $this->assertSame('Item 1', CatalogItem::query()->withoutWorkspaceScope()->where('workspace_id', $workspace->id)->where('code', 'ITEM-1')->value('name_en'));
    }

    public function test_a_code_repeated_inside_the_file_is_flagged_on_the_second_row(): void
    {
        $this->signedInOwner();
        $rows = $this->itemRows(2);
        $rows[2][0] = 'item-1';

        $response = $this->preview($this->xlsx($rows))->assertJsonPath('data.status', 'failed');

        $this->assertSame(3, $response->json('data.errors.0.row'));
    }

    public function test_an_imported_source_ref_cannot_be_imported_again_from_another_file(): void
    {
        [, $workspace] = $this->signedInOwner();
        $email = $this->customerEmail($workspace);
        $first = [self::WITHDRAWAL_HEADERS, ['WD-1', $email, 'Printing', '1', '2', '']];
        $batchId = $this->preview($this->xlsx($first), 'withdrawals')->json('data.id');
        $this->postJson('/api/workspace/finance/imports/'.$batchId.'/commit')->assertOk();

        $second = [self::WITHDRAWAL_HEADERS, ['WD-1', $email, 'Printing again', '1', '2', ''], ['WD-2', $email, 'Other', '1', '1', '']];
        $this->preview($this->xlsx($second), 'withdrawals')->assertJsonPath('data.status', 'failed')->assertJsonPath('data.errors.0.column', 'source_ref');

        $this->assertSame(1, UnbilledCharge::query()->withoutWorkspaceScope()->count());
    }
}
