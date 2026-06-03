<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ListMembersRequest;
use App\Http\Requests\Owner\UpdateMemberStatusRequest;
use App\Http\Resources\MemberResource;
use App\Models\User;
use App\Models\Workspace;
use App\Services\MemberService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function __construct(
        private readonly MemberService $members,
    ) {}

    /**
     * GET /api/workspace/members
     *
     * Paginated, filterable roster of this workspace's members.
     */
    public function index(ListMembersRequest $request): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::success(['members' => [], 'meta' => $this->emptyMeta()]);
        }

        $paginator = $this->members->paginateMembers($workspace, $request->validated());

        return ApiResponse::success([
            'members' => MemberResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/workspace/members/{user}
     *
     * Full detail for a member of THIS workspace only.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error('Member not found.', 404);
        }

        $detail = $this->members->memberDetail($workspace, $user);

        if ($detail === null) {
            return ApiResponse::error('Member not found.', 404);
        }

        return ApiResponse::success($detail);
    }

    /**
     * PUT /api/workspace/members/{user}/status
     *
     * Activate or suspend a member. Suspension cancels their subscription
     * and frees their seat — scoped to the owner's workspace.
     */
    public function updateStatus(UpdateMemberStatusRequest $request, User $user): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error('Member not found.', 404);
        }

        $subscription = $this->members->updateStatus(
            $workspace,
            $user,
            $request->validated()['status'],
        );

        if ($subscription === null) {
            return ApiResponse::error('Member not found.', 404);
        }

        return ApiResponse::success(
            new MemberResource($subscription),
            'Member status updated.',
        );
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
