<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\ImportBatch;

class ImportFormulaRejectedTest extends ImportTestCase
{
    public function test_any_formula_cell_rejects_the_whole_file(): void
    {
        $this->signedInOwner();
        $rows = $this->itemRows(3);
        $rows[2][5] = '=1+1';

        $this->preview($this->xlsx($rows))
            ->assertStatus(422)
            ->assertJsonPath('message', __('messages.import_formula_rejected'));

        $this->assertSame(0, ImportBatch::query()->withoutWorkspaceScope()->count());
    }

    public function test_a_formula_in_the_header_row_is_rejected_too(): void
    {
        $this->signedInOwner();
        $rows = $this->itemRows(1);
        $rows[0][0] = '=A2';

        $this->preview($this->xlsx($rows))->assertStatus(422)->assertJsonPath('message', __('messages.import_formula_rejected'));
    }
}
