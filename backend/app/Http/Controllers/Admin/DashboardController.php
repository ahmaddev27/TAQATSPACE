<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardStatsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardStatsService $stats,
    ) {}

    /**
     * GET /api/admin/stats — platform-wide counters for the admin dashboard.
     */
    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->stats->build());
    }
}
