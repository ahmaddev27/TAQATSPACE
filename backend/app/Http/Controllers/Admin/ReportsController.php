<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ReportsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ReportsController extends Controller
{
    public function __construct(
        private readonly ReportsService $reports,
    ) {}

    /**
     * GET /api/admin/reports — operational + financial tracking aggregates.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success($this->reports->build());
    }
}
