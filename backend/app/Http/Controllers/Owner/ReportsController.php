<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\OwnerReportsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Deeper owner reporting: collections aging, monthly profit-and-loss, and
 * internet-package uptake for the authenticated owner's workspace.
 */
final class ReportsController extends Controller
{
    public function __construct(private readonly OwnerReportsService $reports) {}

    /**
     * GET /api/workspace/reports
     */
    public function index(Request $request): JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 404);
        }

        return ApiResponse::success($this->reports->build($workspace));
    }
}
