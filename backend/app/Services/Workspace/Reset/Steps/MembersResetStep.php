<?php

declare(strict_types=1);

namespace App\Services\Workspace\Reset\Steps;

use App\Contracts\WorkspaceResetStep;
use App\Enums\SeatStatus;
use App\Models\Seat;
use App\Models\Subscription;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;

/**
 * Removes the workspace's subscriptions (its member roster) and frees every
 * occupied seat. Seats themselves are configuration and stay; user accounts are
 * platform identity and are never deleted here.
 */
final class MembersResetStep implements WorkspaceResetStep
{
    public function key(): string
    {
        return 'members';
    }

    public function reset(Workspace $workspace): void
    {
        Subscription::query()->where('workspace_id', $workspace->id)->delete();

        Seat::query()
            ->where('workspace_id', $workspace->id)
            ->where(fn (Builder $q) => $q
                ->whereNotNull('assigned_member_id')
                ->orWhere('status', SeatStatus::Occupied->value))
            ->update(['assigned_member_id' => null, 'status' => SeatStatus::Available->value]);
    }

    public function filePaths(Workspace $workspace): array
    {
        return [];
    }
}
