<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Package\AssignPackageRequest;
use App\Http\Requests\Package\StorePackageRequest;
use App\Http\Requests\Package\UpdatePackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\InternetPackage;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PackageService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PackageController extends Controller
{
    public function __construct(
        private readonly PackageService $packages,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        return ApiResponse::success(
            PackageResource::collection($this->packages->listForWorkspace($workspace)),
        );
    }

    public function store(StorePackageRequest $request): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        $package = $this->packages->create($workspace, $request->validated());

        return ApiResponse::success(
            new PackageResource($package),
            'Package created successfully.',
            201,
        );
    }

    public function update(UpdatePackageRequest $request, InternetPackage $package): JsonResponse
    {
        $this->authorizePackage($request, $package);

        $package = $this->packages->update($package, $request->validated());

        return ApiResponse::success(
            new PackageResource($package),
            'Package updated successfully.',
        );
    }

    public function destroy(Request $request, InternetPackage $package): JsonResponse
    {
        $this->authorizePackage($request, $package);

        $this->packages->delete($package);

        return ApiResponse::message('Package deleted successfully.');
    }

    public function assign(AssignPackageRequest $request, InternetPackage $package): JsonResponse
    {
        $this->authorizePackage($request, $package);

        $memberId = (string) $request->validated()['member_id'];
        $this->guardMemberBelongsToWorkspace($package->workspace_id, $memberId);

        $package = $this->packages->assignMember($package, $memberId);

        return ApiResponse::success(
            new PackageResource($package),
            'Package assigned to member.',
        );
    }

    public function unassign(AssignPackageRequest $request, InternetPackage $package): JsonResponse
    {
        $this->authorizePackage($request, $package);

        $package = $this->packages->unassignMember($package, (string) $request->validated()['member_id']);

        return ApiResponse::success(
            new PackageResource($package),
            'Package unassigned from member.',
        );
    }

    /**
     * Resolve the authenticated owner's workspace or fail with 404 when they
     * have not created one yet.
     */
    private function ownerWorkspace(Request $request): Workspace
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            throw new NotFoundHttpException('No workspace found for this owner.');
        }

        return $workspace;
    }

    /**
     * Ensure the package belongs to the authenticated owner's workspace. A
     * mismatch is treated as "not found" to avoid leaking existence.
     */
    private function authorizePackage(Request $request, InternetPackage $package): void
    {
        if ($package->workspace_id !== $this->ownerWorkspace($request)->id) {
            throw new NotFoundHttpException('Package not found.');
        }
    }

    /**
     * A package may only be assigned to a member who has a subscription to the
     * same workspace.
     */
    private function guardMemberBelongsToWorkspace(string $workspaceId, string $memberId): void
    {
        $belongs = User::query()
            ->where('id', $memberId)
            ->whereHas('subscriptions', static fn ($query) => $query->where('workspace_id', $workspaceId))
            ->exists();

        if (! $belongs) {
            throw new NotFoundHttpException('Member is not part of this workspace.');
        }
    }
}
