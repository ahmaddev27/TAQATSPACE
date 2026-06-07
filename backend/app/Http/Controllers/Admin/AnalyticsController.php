<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AnalyticsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {}

    /**
     * GET /api/admin/analytics — demographic (gender) and geographic (city)
     * distributions across the platform.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success($this->analytics->build());
    }
}
