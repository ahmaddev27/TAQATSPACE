<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Models\CatalogCategory;
use App\Models\ImportBatch;
use App\Services\Finance\Import\XlsxReader;
use Closure;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

class ImportHardeningTest extends ImportTestCase
{
    public function test_a_file_written_with_shared_strings_previews_like_an_inline_one(): void
    {
        $this->signedInOwner();

        $this->preview($this->xlsxShared($this->itemRows(3)))
            ->assertCreated()
            ->assertJsonPath('data.status', 'previewed')
            ->assertJsonPath('data.row_count', 3);
    }

    public function test_a_note_outside_the_table_is_not_a_row_of_required_errors(): void
    {
        $this->signedInOwner();
        $rows = $this->itemRows(2);
        $rows[] = ['', '', '', '', '', '', 'remember to call the supplier'];

        $this->preview($this->xlsx($rows))
            ->assertCreated()
            ->assertJsonPath('data.status', 'previewed')
            ->assertJsonPath('data.row_count', 2);
    }

    public function test_a_file_holding_only_notes_below_the_header_is_empty(): void
    {
        $this->signedInOwner();
        $rows = [self::ITEM_HEADERS, ['', '', '', '', '', '', 'just a note']];

        $this->preview($this->xlsx($rows))->assertStatus(422)->assertJsonPath('message', __('messages.import_empty'));
    }

    public function test_cells_outside_the_known_columns_are_not_parked_on_disk(): void
    {
        [, $workspace] = $this->signedInOwner();
        $rows = $this->itemRows(1);
        $rows[0][] = 'secret-notes';
        $rows[1][] = 'private@example.test';

        $batchId = $this->preview($this->xlsx($rows))->assertCreated()->json('data.id');

        $payload = Storage::disk('local')->get(ImportBatch::query()->withoutWorkspaceScope()->findOrFail($batchId)->payload_path);
        $this->assertStringNotContainsString('private@example.test', (string) $payload);
        $this->assertSame($workspace->id, ImportBatch::query()->withoutWorkspaceScope()->findOrFail($batchId)->workspace_id);
    }

    public function test_a_preview_racing_a_commit_never_sets_a_committed_batch_back_to_previewed(): void
    {
        $this->signedInOwner();
        $rows = $this->itemRows(2);
        $commitNow = false;
        // Installed first: the route caches its controller, so a later binding would not be used.
        $this->spyOnReader(function () use (&$commitNow): void {
            if ($commitNow) {
                ImportBatch::query()->withoutWorkspaceScope()->update(['status' => ImportStatus::Committed]);
            }
        });
        $this->preview($this->xlsx($rows))->assertCreated();
        $commitNow = true;

        $this->preview($this->xlsx($rows))->assertStatus(409)->assertJsonPath('message', __('messages.import_duplicate_file'));

        $this->assertSame(ImportStatus::Committed, ImportBatch::query()->withoutWorkspaceScope()->firstOrFail()->status);
    }

    public function test_a_re_preview_with_errors_removes_the_file_the_earlier_preview_left(): void
    {
        [, $workspace] = $this->signedInOwner();
        $rows = $this->itemRows(2);
        $first = $this->preview($this->xlsx($rows))->assertJsonPath('data.status', 'previewed');
        $path = ImportBatch::query()->withoutWorkspaceScope()->findOrFail($first->json('data.id'))->payload_path;
        Storage::disk('local')->assertExists($path);

        CatalogCategory::query()->withoutWorkspaceScope()->where('workspace_id', $workspace->id)->update(['archived_at' => now()]);
        $this->preview($this->xlsx($rows))->assertJsonPath('data.status', 'failed');

        Storage::disk('local')->assertMissing($path);
        $this->assertNull(ImportBatch::query()->withoutWorkspaceScope()->findOrFail($first->json('data.id'))->payload_path);
    }

    public function test_the_prune_command_removes_only_old_previewed_batches_and_their_files(): void
    {
        [$owner, $workspace] = $this->signedInOwner();
        $old = $this->batch($workspace->id, $owner->id, ImportStatus::Previewed, now()->subDays(8), 'old');
        $recent = $this->batch($workspace->id, $owner->id, ImportStatus::Previewed, now()->subDays(6), 'recent');
        $committed = $this->batch($workspace->id, $owner->id, ImportStatus::Committed, now()->subDays(30), 'committed');

        $this->artisan('imports:prune')->assertSuccessful();

        $this->assertModelMissing($old);
        Storage::disk('local')->assertMissing('imports/old.json');
        $this->assertModelExists($recent);
        Storage::disk('local')->assertExists('imports/recent.json');
        $this->assertModelExists($committed);
        Storage::disk('local')->assertExists('imports/committed.json');
    }

    public function test_the_prune_command_is_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->pluck('command')->implode(' ');

        $this->assertStringContainsString('imports:prune', $commands);
    }

    public function test_the_commit_route_has_its_own_throttle(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())->first(
            static fn ($route): bool => $route->uri() === 'api/workspace/finance/imports/{id}/commit' && in_array('POST', $route->methods(), true),
        );

        $this->assertContains('throttle:10,1,import-commit', $route->gatherMiddleware());
    }

    private function batch(string $workspaceId, string $userId, ImportStatus $status, \DateTimeInterface $touched, string $name): ImportBatch
    {
        Storage::disk('local')->put('imports/'.$name.'.json', '[]');

        $batch = new ImportBatch;
        $batch->forceFill([
            'workspace_id' => $workspaceId, 'type' => ImportType::CatalogItems, 'file_hash' => hash('sha256', $name), 'status' => $status,
            'row_count' => 1, 'errors' => [], 'payload_path' => 'imports/'.$name.'.json', 'created_by' => $userId,
            'created_at' => $touched, 'updated_at' => $touched,
        ])->save();

        return $batch;
    }

    /** Runs $afterRead right after the reader has parsed a file, i.e. between the early duplicate check and the save. */
    private function spyOnReader(Closure $afterRead): void
    {
        $this->app->instance(XlsxReader::class, new class($afterRead) extends XlsxReader
        {
            public function __construct(private readonly Closure $afterRead) {}

            public function read(string $path): array
            {
                $parsed = parent::read($path);
                ($this->afterRead)();

                return $parsed;
            }
        });
    }
}
