<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateWorkspaceFeaturesRequest;
use App\Models\Workspace;
use App\Services\FeatureFlagService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class WorkspaceFeatureController extends Controller
{
    public function __construct(private readonly FeatureFlagService $features) {}

    /**
     * PUT /api/admin/workspaces/{workspace}/features — enable/disable modules.
     */
    public function update(UpdateWorkspaceFeaturesRequest $request, Workspace $workspace): JsonResponse
    {
        $flags = $this->features->update($workspace, $request->changes(), $request->user());

        return ApiResponse::success(['features' => $flags], __('messages.workspace_features_updated'));
    }
}
