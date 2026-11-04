<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner\Concerns;

use App\Models\Router;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

trait ResolvesOwnedRouter
{
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
