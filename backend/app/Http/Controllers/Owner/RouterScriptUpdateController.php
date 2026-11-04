<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\PushRouterScriptUpdateRequest;
use App\Models\Router;
use App\Services\Router\RouterScriptUpdateService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RouterScriptUpdateController extends Controller
{
    public function __construct(private readonly RouterScriptUpdateService $updates) {}

    /** GET /api/workspace/routers/{router}/script-update — owner only; is a newer script version available? */
    public function show(Request $request, string $router): JsonResponse
    {
        $target = $this->ownedRouter($request, $router);

        if ($target instanceof JsonResponse) {
            return $target;
        }

        return ApiResponse::success($this->updates->availableFor($target));
    }

    /**
     * POST /api/workspace/routers/{router}/update-script — owner only.
     *
     * The owner's confirmation: queues the `update-script` commands. `full`
     * re-pushes every script even when the version already matches.
     */
    public function store(PushRouterScriptUpdateRequest $request, string $router): JsonResponse
    {
        $target = $this->ownedRouter($request, $router);

        if ($target instanceof JsonResponse) {
            return $target;
        }

        $commands = $this->updates->push($target, $request->wantsFullUpdate());

        return ApiResponse::success(
            ['queued' => count($commands), 'command_ids' => array_map(fn ($command): string => $command->getKey(), $commands)],
            __('messages.router_script_update_queued'),
            202,
        );
    }

    private function ownedRouter(Request $request, string $router): Router|JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null || Gate::denies('manage-workspace', $workspace)) {
            return ApiResponse::error(__('messages.unauthorized_action'), 403);
        }

        return Router::query()->withoutWorkspaceScope()->where('workspace_id', $workspace->getKey())->find($router)
            ?? ApiResponse::error(__('messages.router_not_found'), 404);
    }
}
