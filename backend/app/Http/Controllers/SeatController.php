<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Seat\AssignSeatRequest;
use App\Http\Requests\Seat\StoreSeatRequest;
use App\Http\Requests\Seat\UpdateSeatRequest;
use App\Http\Resources\SeatResource;
use App\Models\Seat;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SeatService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SeatController extends Controller
{
    public function __construct(
        private readonly SeatService $seats,
    ) {}

    /**
     * Seat map for a workspace. Public visitors see seat state; only the owner
     * or an admin sees the assignee's name.
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $map = $this->seats->seatMap($workspace);

        $canSeeAssignee = $request->user() !== null
            && Gate::forUser($request->user())->allows('manage-workspace', $workspace);

        $seats = $canSeeAssignee
            ? SeatResource::collection($map['seats'])
            : SeatResource::collection($map['seats']->each->unsetRelation('assignedMember'));

        return ApiResponse::success([
            'seats' => $seats,
            'summary' => $map['summary'],
        ]);
    }

    public function store(StoreSeatRequest $request): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        $seat = $this->seats->create($workspace, $request->validated());

        return ApiResponse::success(
            new SeatResource($seat->load('assignedMember')),
            'Seat created.',
            201,
        );
    }

    public function update(UpdateSeatRequest $request, Seat $seat): JsonResponse
    {
        $this->authorizeSeat($request, $seat);

        $seat = $this->seats->update($seat, $request->validated());

        return ApiResponse::success(new SeatResource($seat->load('assignedMember')));
    }

    public function assign(AssignSeatRequest $request, Seat $seat): JsonResponse
    {
        $this->authorizeSeat($request, $seat);

        /** @var User $member */
        $member = User::query()->findOrFail($request->validated('member_id'));

        $seat = $this->seats->assign($seat, $member);

        return ApiResponse::success(
            new SeatResource($seat->load('assignedMember')),
            'Seat assigned.',
        );
    }

    public function unassign(Request $request, Seat $seat): JsonResponse
    {
        $this->authorizeSeat($request, $seat);

        $seat = $this->seats->unassign($seat);

        return ApiResponse::success(
            new SeatResource($seat->load('assignedMember')),
            'Seat unassigned.',
        );
    }

    public function destroy(Request $request, Seat $seat): JsonResponse
    {
        $this->authorizeSeat($request, $seat);

        $this->seats->delete($seat);

        return ApiResponse::message(__('messages.seat_deleted'));
    }

    /**
     * Resolve the authenticated owner's workspace, or 404 if they have none.
     */
    private function ownerWorkspace(Request $request): Workspace
    {
        $workspace = $request->user()?->workspace;

        if ($workspace === null) {
            abort(404, __('messages.no_workspace_found_account'));
        }

        return $workspace;
    }

    /**
     * Ensure the actor may manage the workspace that owns this seat.
     */
    private function authorizeSeat(Request $request, Seat $seat): void
    {
        Gate::forUser($request->user())->authorize('manage-workspace', $seat->workspace);
    }
}
