<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Branch\BranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Models\Workspace;
use App\Services\BranchService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Branches of the authenticated owner's workspace. Branches are looked up by id
 * inside that workspace only, so another tenant's branch answers 404.
 */
class BranchController extends Controller
{
    public function __construct(
        private readonly BranchService $branches,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            BranchResource::collection($this->branches->list($this->ownerWorkspace($request))),
        );
    }

    public function store(BranchRequest $request): JsonResponse
    {
        $branch = $this->branches->create($this->ownerWorkspace($request), $request->validated());

        return ApiResponse::success(new BranchResource($branch), 'Branch created.', 201);
    }

    public function update(BranchRequest $request, string $branch): JsonResponse
    {
        $updated = $this->branches->update($this->findBranch($request, $branch), $request->validated());

        return ApiResponse::success(new BranchResource($updated));
    }

    public function destroy(Request $request, string $branch): JsonResponse
    {
        $this->branches->delete($this->findBranch($request, $branch));

        return ApiResponse::message(__('messages.branch_deleted'));
    }

    private function findBranch(Request $request, string $id): Branch
    {
        return Branch::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $this->ownerWorkspace($request)->id)
            ->findOrFail($id);
    }

    private function ownerWorkspace(Request $request): Workspace
    {
        $workspace = $request->user()?->workspace;

        if ($workspace === null) {
            abort(404, __('messages.no_workspace_found_account'));
        }

        return $workspace;
    }
}
