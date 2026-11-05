<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Exceptions\RecalculationInProgressException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\RecalculateStatsRequest;
use App\Http\Resources\RecalculationStatusResource;
use App\Models\Workspace;
use App\Services\Network\StatsRecalculationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** The owner's "Tools" tab. The workspace is always the caller's own, so there is no id to tamper with. */
class ToolsController extends Controller
{
    public function __construct(private readonly StatsRecalculationService $recalculation) {}

    /** POST /api/workspace/tools/recalculate — owner only; queues a recalculation of a date range. */
    public function recalculate(RecalculateStatsRequest $request): JsonResponse
    {
        $workspace = $this->ownedWorkspace($request);

        if ($workspace instanceof JsonResponse) {
            return $workspace;
        }

        try {
            $status = $this->recalculation->request($workspace, $request->validated('from'), $request->validated('to'), $request->user());
        } catch (RecalculationInProgressException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success(
            (new RecalculationStatusResource($status))->resolve($request),
            __('messages.stats_recalculation_started'),
            202,
        );
    }

    /** GET /api/workspace/tools/recalculate/status — owner only; the latest recalculation. */
    public function status(Request $request): JsonResponse
    {
        $workspace = $this->ownedWorkspace($request);

        if ($workspace instanceof JsonResponse) {
            return $workspace;
        }

        return ApiResponse::success((new RecalculationStatusResource($this->recalculation->status($workspace)))->resolve($request));
    }

    private function ownedWorkspace(Request $request): Workspace|JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        return Gate::denies('manage-workspace', $workspace)
            ? ApiResponse::error(__('messages.unauthorized_action'), 403)
            : $workspace;
    }
}
