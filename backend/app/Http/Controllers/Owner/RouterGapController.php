<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ListRouterGapsRequest;
use App\Http\Resources\RouterGapResource;
use App\Repositories\Eloquent\RouterGapRepository;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RouterGapController extends Controller
{
    public function __construct(private readonly RouterGapRepository $gaps) {}

    /** GET /api/workspace/network/gaps — owner only; the workspace's router gaps, newest first. */
    public function index(ListRouterGapsRequest $request): JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null || Gate::denies('manage-workspace', $workspace)) {
            return ApiResponse::error(__('messages.unauthorized_action'), 403);
        }

        $paginator = $this->gaps->paginate((string) $workspace->getKey(), $request->validated());

        return ApiResponse::success([
            'gaps' => RouterGapResource::collection($paginator->items())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
