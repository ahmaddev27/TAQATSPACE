<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\CatalogCategory;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsXlsx;

abstract class ImportTestCase extends CatalogTestCase
{
    use BuildsXlsx;

    protected const PREVIEW = '/api/workspace/finance/imports/preview';

    protected const ITEM_HEADERS = ['code', 'category_code', 'name_ar', 'name_en', 'unit', 'price'];

    protected const WITHDRAWAL_HEADERS = ['source_ref', 'customer_email', 'description', 'qty', 'unit_price', 'occurred_at'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** @return array{0: User, 1: Workspace} */
    protected function signedInOwner(): array
    {
        [$owner, $workspace] = $this->owningWorkspace();
        Sanctum::actingAs($owner);
        CatalogCategory::factory()->create(['workspace_id' => $workspace->id, 'code' => 'DRINKS']);

        return [$owner, $workspace];
    }

    /** @return list<list<string>> */
    protected function itemRows(int $count, int $from = 1): array
    {
        $rows = [self::ITEM_HEADERS];
        for ($index = $from; $index < $from + $count; $index++) {
            $rows[] = ['ITEM-'.$index, 'DRINKS', 'صنف '.$index, 'Item '.$index, 'piece', '2.50'];
        }

        return $rows;
    }

    protected function preview(UploadedFile $file, string $type = 'catalog_items'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(self::PREVIEW, ['type' => $type, 'file' => $file]);
    }

    protected function customerEmail(Workspace $workspace): string
    {
        $member = User::factory()->create();
        Subscription::factory()->create(['member_id' => $member->id, 'workspace_id' => $workspace->id]);

        return $member->email;
    }
}
