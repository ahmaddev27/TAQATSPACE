<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\OwnerDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly OwnerDashboardService $dashboard,
    ) {}

    /**
     * GET /api/workspace/dashboard
     *
     * Aggregated, 5-minute-cached snapshot of the authed owner's workspace.
     * Owners without a workspace yet receive a zeroed 200 payload.
     */
    public function ownerStats(Request $request): JsonResponse
    {
        $stats = $this->dashboard->statsFor($request->user()->workspace);

        return ApiResponse::success($stats);
    }
}
