<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\MemberDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly MemberDashboardService $dashboard,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->dashboard->summary($request->user()),
        );
    }
}
