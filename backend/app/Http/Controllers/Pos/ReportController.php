<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pos;

use App\Enums\PosPermission;
use App\Services\Pos\PosReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only POS sales reports for a workspace, gated behind `pos_view_reports`
 * (owners always pass). Aggregates paid, non-refunded orders over an optional
 * date range, defaulting to the trailing 30 days.
 */
class ReportController extends PosController
{
    public function __construct(
        private readonly PosReportService $reports,
    ) {}

    /**
     * GET /api/pos/reports?from=&to=
     */
    public function index(Request $request): JsonResponse
    {
        $workspace = $this->posWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $this->requirePosPermission($request->user(), PosPermission::ViewReports);

        return ApiResponse::success(
            $this->reports->build(
                $workspace,
                $request->query('from') !== null ? (string) $request->query('from') : null,
                $request->query('to') !== null ? (string) $request->query('to') : null,
            ),
        );
    }
}
