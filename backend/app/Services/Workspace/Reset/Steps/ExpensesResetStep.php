<?php

declare(strict_types=1);

namespace App\Services\Workspace\Reset\Steps;

use App\Contracts\WorkspaceResetStep;
use App\Models\Expense;
use App\Models\Workspace;

final class ExpensesResetStep implements WorkspaceResetStep
{
    public function key(): string
    {
        return 'expenses';
    }

    public function reset(Workspace $workspace): void
    {
        Expense::query()->where('workspace_id', $workspace->id)->delete();
    }

    public function filePaths(Workspace $workspace): array
    {
        return [];
    }
}
