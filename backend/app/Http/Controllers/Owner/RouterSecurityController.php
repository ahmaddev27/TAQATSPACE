<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Exceptions\RouterRotationInProgressException;
use App\Http\Controllers\Controller;
use App\Http\Resources\RouterKeyResource;
use App\Models\Router;
use App\Services\Router\RouterSecurityService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RouterSecurityController extends Controller
{
    public function __construct(private readonly RouterSecurityService $security) {}

    /**
     * POST /api/workspace/routers/{router}/unlock — owner only, recent 2FA.
     *
     * Clears the bound serial number so a replacement router can bind itself.
     */
    public function unlock(Request $request, string $router): JsonResponse
    {
        $target = $this->ownedRouter($request, $router);

        if ($target instanceof JsonResponse) {
            return $target;
        }

        $this->security->unlock($target);

        return ApiResponse::success(null, __('messages.router_unlocked'));
    }

    /**
     * POST /api/workspace/routers/{router}/rotate-key — owner only, recent 2FA.
     *
     * The new key is in this response only; the router receives it via `set-key`.
     */
    public function rotateKey(Request $request, string $router): JsonResponse
    {
        $target = $this->ownedRouter($request, $router);

        if ($target instanceof JsonResponse) {
            return $target;
        }

        try {
            $rotated = $this->security->rotateKey($target);
        } catch (RouterRotationInProgressException) {
            return ApiResponse::error(__('messages.router_rotation_in_progress'), 409);
        }

        return ApiResponse::success(new RouterKeyResource($rotated), __('messages.router_key_rotated'));
    }

    /** Routers of other workspaces are 404, never 403, so their ids stay private. */
    private function ownedRouter(Request $request, string $id): Router|JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null || Gate::denies('manage-workspace', $workspace)) {
            return ApiResponse::error(__('messages.unauthorized_action'), 403);
        }

        return Router::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $workspace->getKey())
            ->find($id) ?? ApiResponse::error(__('messages.router_not_found'), 404);
    }
}
