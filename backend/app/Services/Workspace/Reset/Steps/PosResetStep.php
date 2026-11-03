<?php

declare(strict_types=1);

namespace App\Services\Workspace\Reset\Steps;

use App\Contracts\WorkspaceResetStep;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosPayment;
use App\Models\Workspace;

/** Clears sales history only; the product catalogue and stock are configuration. */
final class PosResetStep implements WorkspaceResetStep
{
    public function key(): string
    {
        return 'pos';
    }

    public function reset(Workspace $workspace): void
    {
        $orderIds = PosOrder::query()->where('workspace_id', $workspace->id)->select('id');

        PosPayment::query()->whereIn('pos_order_id', $orderIds)->delete();
        PosOrderItem::query()->whereIn('pos_order_id', $orderIds)->delete();
        PosOrder::query()->where('workspace_id', $workspace->id)->delete();
    }

    public function filePaths(Workspace $workspace): array
    {
        return [];
    }
}
