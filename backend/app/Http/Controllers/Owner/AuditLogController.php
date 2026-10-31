<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListAuditLogsRequest;
use App\Http\Resources\AuditLogResource;
use App\Repositories\Eloquent\AuditLogRepository;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Read-only audit log of the authenticated owner's own workspace. There is
 * deliberately no write endpoint: the log is append-only.
 */
class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogRepository $logs,
    ) {}

    /**
     * GET /api/workspace/audit-logs
     */
    public function index(ListAuditLogsRequest $request): JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        // The workspace comes from the session, never from the query string.
        $paginator = $this->logs->paginate($workspace->id, $request->safe()->except('workspace_id'));

        return ApiResponse::success([
            'logs' => AuditLogResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
