<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Seat\AssignSeatRequest;
use App\Http\Requests\Seat\BulkStoreSeatRequest;
use App\Http\Requests\Seat\SeatFilterRequest;
use App\Http\Requests\Seat\StoreSeatRequest;
use App\Http\Requests\Seat\UpdateSeatRequest;
use App\Http\Resources\SeatResource;
use App\Models\Seat;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Seat\SeatMonthOccupancy;
use App\Services\SeatService;
use Carbon\CarbonImmutable;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SeatController extends Controller
{
    public function __construct(
        private readonly SeatService $seats,
        private readonly SeatMonthOccupancy $occupancy,
    ) {}

    /**
     * Seat map for a workspace. Public visitors see seat state; only the owner
     * or an admin sees the assignee's name.
     */
    public function index(SeatFilterRequest $request, Workspace $workspace): JsonResponse
    {
        $map = $this->seats->seatMap($workspace, $request->safe()->only(['branch_id', 'zone']));

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

    /**
     * GET /api/workspace/seats — the authenticated owner's own seat map, always
     * including the assignee (name + avatar) so the seat board shows who sits
     * where. Unlike the public index, this never strips the occupant.
     */
    public function ownerIndex(SeatFilterRequest $request): JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 404);
        }

        $map = $this->seats->seatMap($workspace, $request->safe()->only(['branch_id', 'zone']));

        return ApiResponse::success([
            'seats' => SeatResource::collection($map['seats']),
            'summary' => $map['summary'],
        ]);
    }

    /**
     * GET /api/workspace/seats/occupancy?month=YYYY-MM — the "Month" tab: days
     * each station was held, from the subscriptions pointing at it.
     */
    public function monthOccupancy(SeatFilterRequest $request): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);
        $month = CarbonImmutable::createFromFormat('!Y-m', $request->validated('month') ?? now()->format('Y-m'));

        return ApiResponse::success(
            $this->occupancy->forMonth($workspace, $month, $request->safe()->only(['branch_id', 'zone'])),
        );
    }

    /** POST /api/workspace/seats/bulk — all-or-nothing batch of up to 200 stations. */
    public function bulkStore(BulkStoreSeatRequest $request): JsonResponse
    {
        $created = $this->seats->bulkCreate($this->ownerWorkspace($request), $request->validated());

        return ApiResponse::success(
            ['seats' => SeatResource::collection($created), 'created' => $created->count()],
            'Seats created.',
            201,
        );
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
