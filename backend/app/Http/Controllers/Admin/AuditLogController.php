<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListAuditLogsRequest;
use App\Http\Resources\AuditLogResource;
use App\Repositories\Eloquent\AuditLogRepository;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Read-only platform-wide audit log (optionally narrowed by `workspace_id`).
 */
class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogRepository $logs,
    ) {}

    /**
     * GET /api/admin/audit-logs
     */
    public function index(ListAuditLogsRequest $request): JsonResponse
    {
        $paginator = $this->logs->paginate(null, $request->validated());

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
