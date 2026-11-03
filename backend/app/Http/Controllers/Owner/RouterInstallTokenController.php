<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\RouterInstallTokenResource;
use App\Models\Router;
use App\Services\Router\RouterInstallerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RouterInstallTokenController extends Controller
{
    public function __construct(private readonly RouterInstallerService $installer) {}

    /**
     * POST /api/workspace/routers/{router}/install-token — owner only.
     *
     * Issues a fresh 30-minute token (invalidating earlier ones) and returns the
     * ready-to-paste RouterOS command. Routers of other workspaces are 404.
     */
    public function store(Request $request, string $router): JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null || Gate::denies('manage-workspace', $workspace)) {
            return ApiResponse::error(__('messages.unauthorized_action'), 403);
        }

        $target = Router::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $workspace->getKey())
            ->find($router);

        if ($target === null) {
            return ApiResponse::error(__('messages.router_not_found'), 404);
        }

        $issued = $this->installer->issueToken($target);

        return ApiResponse::success(new RouterInstallTokenResource($issued), __('messages.router_install_token_issued'), 201);
    }
}
