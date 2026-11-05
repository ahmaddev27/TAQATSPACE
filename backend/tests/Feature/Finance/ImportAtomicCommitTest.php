<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\AuditLog;
use App\Models\CatalogCategory;
use App\Models\CatalogItem;
use App\Models\ImportBatch;
use App\Models\UnbilledCharge;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;

class ImportAtomicCommitTest extends ImportTestCase
{
    private function commitUrl(string $batchId): string
    {
        return '/api/workspace/finance/imports/'.$batchId.'/commit';
    }

    public function test_a_commit_writes_every_row_and_is_audited(): void
    {
        $this->signedInOwner();
        $batchId = $this->preview($this->xlsx($this->itemRows(5)))->json('data.id');

        $this->postJson($this->commitUrl($batchId))
            ->assertOk()
            ->assertJsonPath('data.status', 'committed');

        $this->assertSame(5, CatalogItem::query()->withoutWorkspaceScope()->count());
        $this->assertTrue(AuditLog::query()->where('action', 'finance.import.committed')->exists());
        $this->assertNotNull(ImportBatch::query()->withoutWorkspaceScope()->find($batchId)->committed_at);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_withdrawals_are_recorded_as_import_charges(): void
    {
        [, $workspace] = $this->signedInOwner();
        $email = $this->customerEmail($workspace);
        $rows = [self::WITHDRAWAL_HEADERS, ['WD-1', $email, 'Printing', '3', '1.50', '2026-01-31'], ['WD-2', strtoupper($email), 'Coffee', '1', '4', '']];
        $batchId = $this->preview($this->xlsx($rows), 'withdrawals')->assertCreated()->assertJsonPath('data.error_count', 0)->json('data.id');

        $this->postJson($this->commitUrl($batchId))->assertOk();

        $charges = UnbilledCharge::query()->withoutWorkspaceScope()->orderBy('source_ref')->get();
        $this->assertCount(2, $charges);
        $this->assertSame('import', $charges[0]->source->value);
        $this->assertSame(4.5, (float) $charges[0]->amount);
        $this->assertSame('2026-01-31', $charges[0]->occurred_at->toDateString());
    }

    public function test_one_invalid_row_means_the_commit_is_rejected_and_nothing_is_written(): void
    {
        $this->signedInOwner();
        $rows = $this->itemRows(4);
        $rows[3][1] = 'NO-SUCH-CATEGORY';
        $batchId = $this->preview($this->xlsx($rows))->assertJsonPath('data.status', 'failed')->json('data.id');

        $this->postJson($this->commitUrl($batchId))->assertStatus(422);

        $this->assertSame(0, CatalogItem::query()->withoutWorkspaceScope()->count());
    }

    public function test_data_that_changed_after_the_preview_rolls_the_whole_commit_back(): void
    {
        [, $workspace] = $this->signedInOwner();
        $batchId = $this->preview($this->xlsx($this->itemRows(3)))->json('data.id');
        CatalogItem::factory()->create([
            'workspace_id' => $workspace->id,
            'catalog_category_id' => CatalogCategory::query()->withoutWorkspaceScope()->where('workspace_id', $workspace->id)->value('id'),
            'code' => 'ITEM-3',
        ]);

        $this->postJson($this->commitUrl($batchId))->assertStatus(422)->assertJsonStructure(['errors' => ['rows']]);

        $this->assertSame(1, CatalogItem::query()->withoutWorkspaceScope()->count());
        $this->assertSame('failed', ImportBatch::query()->withoutWorkspaceScope()->find($batchId)->status->value);
    }

    public function test_a_failure_while_writing_leaves_no_partial_rows(): void
    {
        $this->signedInOwner();
        $batchId = $this->preview($this->xlsx($this->itemRows(3)))->json('data.id');
        $writes = 0;
        CatalogItem::creating(function () use (&$writes): void {
            if (++$writes === 3) {
                throw new RuntimeException('disk full');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->postJson($this->commitUrl($batchId));
            $this->fail('The commit should have failed.');
        } catch (RuntimeException) {
            // expected: the third write blew up
        }

        $this->assertSame(0, CatalogItem::query()->withoutWorkspaceScope()->count());
        $this->assertSame('previewed', ImportBatch::query()->withoutWorkspaceScope()->find($batchId)->status->value);
    }

    public function test_another_workspaces_batch_is_not_found(): void
    {
        $this->signedInOwner();
        $batchId = $this->preview($this->xlsx($this->itemRows(1)))->json('data.id');

        [$otherOwner] = $this->owningWorkspace();
        Sanctum::actingAs($otherOwner);

        $this->postJson($this->commitUrl($batchId))->assertNotFound();
    }
}
