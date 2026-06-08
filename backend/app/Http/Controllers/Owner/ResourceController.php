<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ListResourcesRequest;
use App\Http\Requests\Owner\StoreResourceRequest;
use App\Http\Requests\Owner\UpdateResourceRequest;
use App\Http\Resources\ResourceResource;
use App\Models\Resource;
use App\Models\Workspace;
use App\Services\ResourceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Resource management for the authenticated owner's workspace ONLY (meeting
 * rooms, private offices, equipment, parking…). Every action is scoped to that
 * workspace; resources on another workspace are never listed or mutated.
 */
class ResourceController extends Controller
{
    public function __construct(
        private readonly ResourceService $resources,
    ) {}

    /**
     * GET /api/workspace/resources
     *
     * Paginated, filterable resources (type + status) plus a by-status counts
     * summary.
     */
    public function index(ListResourcesRequest $request): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::success([
                'resources' => [],
                'summary' => ['total' => 0, 'by_status' => []],
                'meta' => $this->emptyMeta(),
            ]);
        }

        $paginator = $this->resources->paginateForWorkspace($workspace, $request->validated());

        return ApiResponse::success([
            'resources' => ResourceResource::collection($paginator->items()),
            'summary' => $this->resources->summaryForWorkspace($workspace),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/workspace/resources
     */
    public function store(StoreResourceRequest $request): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $resource = $this->resources->create($workspace, $request->validated());

        return ApiResponse::success(
            new ResourceResource($resource),
            __('messages.resource_created'),
            201,
        );
    }

    /**
     * PUT /api/workspace/resources/{resource}
     */
    public function update(UpdateResourceRequest $request, Resource $resource): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        try {
            $updated = $this->resources->update($workspace, $resource, $request->validated());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }

        return ApiResponse::success(
            new ResourceResource($updated),
            __('messages.resource_updated'),
        );
    }

    /**
     * DELETE /api/workspace/resources/{resource}
     */
    public function destroy(Request $request, Resource $resource): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        try {
            $this->resources->delete($workspace, $resource);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }

        return ApiResponse::success(null, __('messages.resource_deleted'));
    }

    private function ownerWorkspace(Request $request): ?Workspace
    {
        return $request->user()->workspace;
    }

    /**
     * @return array<string, int>
     */
    private function emptyMeta(): array
    {
        return [
            'current_page' => 1,
            'per_page' => 15,
            'total' => 0,
            'last_page' => 1,
        ];
    }
}
