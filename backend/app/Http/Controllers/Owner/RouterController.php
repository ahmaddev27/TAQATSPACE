<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Owner\Concerns\ResolvesOwnedRouter;
use App\Http\Requests\Owner\StoreRouterRequest;
use App\Http\Resources\RouterResource;
use App\Services\Router\RouterScriptUpdateService;
use App\Services\Router\RouterService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RouterController extends Controller
{
    use ResolvesOwnedRouter;

    public function __construct(
        private readonly RouterService $routers,
        private readonly RouterScriptUpdateService $updates,
    ) {}

    /** GET /api/workspace/routers — owner only; the workspace's routers, without command history. */
    public function index(Request $request): JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null || Gate::denies('manage-workspace', $workspace)) {
            return ApiResponse::error(__('messages.unauthorized_action'), 403);
        }

        $routers = $this->routers->listFor($workspace);

        return ApiResponse::success(RouterResource::collection($routers)->resolve($request));
    }

    /** POST /api/workspace/routers — owner only. The key is issued with the install command, never here. */
    public function store(StoreRouterRequest $request): JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null || Gate::denies('manage-workspace', $workspace)) {
            return ApiResponse::error(__('messages.unauthorized_action'), 403);
        }

        $created = $this->routers->create($workspace, (string) $request->validated('name'));
        $created->router->setRelation('workspace', $workspace);

        return ApiResponse::success((new RouterResource($created->router))->resolve($request), __('messages.router_created'), 201);
    }

    /** GET /api/workspace/routers/{router} — owner only; one router with recent commands and script update state. */
    public function show(Request $request, string $router): JsonResponse
    {
        $target = $this->ownedRouter($request, $router);

        if ($target instanceof JsonResponse) {
            return $target;
        }

        $detail = $this->routers->withRecentCommands($target);

        return ApiResponse::success([
            ...(new RouterResource($detail))->resolve($request),
            'script_update' => $this->updates->availableFor($detail),
        ]);
    }
}
