<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminSubscriptionResource;
use App\Services\Admin\AdminSubscriptionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly AdminSubscriptionService $subscriptions,
    ) {}

    /**
     * GET /api/admin/subscriptions — read-only tracking list.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'workspace_id', 'member', 'date_from', 'date_to', 'per_page']);

        $paginator = $this->subscriptions->paginate($filters);

        return ApiResponse::success(
            AdminSubscriptionResource::collection($paginator)->response()->getData(true),
        );
    }
}
