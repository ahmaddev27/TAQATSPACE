<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ApprovalRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Filtered, paginated reads of approval requests. Workspace and requester are
 * eager-loaded so a page costs a constant number of queries.
 */
class ApprovalRequestRepository
{
    /**
     * @param  array<string, mixed>  $filters  status, type, per_page
     */
    public function paginate(?string $workspaceId, array $filters): LengthAwarePaginator
    {
        return ApprovalRequest::query()
            ->with(['workspace:id,name,total_seats,working_hours', 'requester:id,name,email'])
            ->when($workspaceId, fn ($q, string $id) => $q->where('workspace_id', $id))
            ->when($filters['status'] ?? null, fn ($q, string $v) => $q->where('status', $v))
            ->when($filters['type'] ?? null, fn ($q, string $v) => $q->where('type', $v))
            ->latest()
            ->paginate(min((int) ($filters['per_page'] ?? 20), 100));
    }
}
