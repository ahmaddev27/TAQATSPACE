<?php

declare(strict_types=1);

namespace App\Services\Workspace\Reset\Steps;

use App\Contracts\WorkspaceResetStep;
use App\Models\BookingRequest;
use App\Models\Workspace;

final class BookingsResetStep implements WorkspaceResetStep
{
    public function key(): string
    {
        return 'bookings';
    }

    public function reset(Workspace $workspace): void
    {
        BookingRequest::query()->where('workspace_id', $workspace->id)->delete();
    }

    public function filePaths(Workspace $workspace): array
    {
        return [];
    }
}
