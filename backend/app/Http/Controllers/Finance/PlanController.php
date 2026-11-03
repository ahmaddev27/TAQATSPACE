<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListPlansRequest;
use App\Http\Requests\Finance\StorePlanRequest;
use App\Http\Requests\Finance\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Repositories\Eloquent\PlanRepository;
use App\Services\Finance\PlanAccess;
use App\Services\Finance\PlanService;
use App\Support\ApiResponse;
use App\Support\Tenancy\CurrentWorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Financial plans of the request's workspace. Routes sit behind the
 * `finance_v2` flag and the workspace context, so the workspace is always set.
 */
class PlanController extends Controller
{
    public function __construct(
        private readonly PlanService $plans,
        private readonly PlanRepository $repository,
        private readonly PlanAccess $access,
        private readonly CurrentWorkspaceContext $context,
    ) {}

    /** GET /api/workspace/finance/plans */
    public function index(ListPlansRequest $request): JsonResponse
    {
        $paginator = $this->repository->paginate($this->context->workspace(), $request->validated());

        return ApiResponse::success([
            'items' => PlanResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /** GET /api/workspace/finance/plans/{id} */
    public function show(Request $request, string $id): JsonResponse
    {
        abort_unless($this->access->canView($request->user()), 403, __('messages.insufficient_permissions'));

        return ApiResponse::success(new PlanResource($this->repository->find($this->context->workspace(), $id)));
    }

    /** POST /api/workspace/finance/plans */
    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = $this->plans->create($this->context->workspace(), $request->validated(), $request->user());

        return ApiResponse::success(new PlanResource($plan), __('messages.plan_created'), 201);
    }

    /** PUT /api/workspace/finance/plans/{id} */
    public function update(UpdatePlanRequest $request, string $id): JsonResponse
    {
        $plan = $this->repository->find($this->context->workspace(), $id);
        $updated = $this->plans->update($plan, $request->validated(), $request->user());

        return ApiResponse::success(new PlanResource($updated), __('messages.plan_updated'));
    }

    /** POST /api/workspace/finance/plans/{id}/archive */
    public function archive(Request $request, string $id): JsonResponse
    {
        abort_unless($this->access->canManage($request->user()), 403, __('messages.insufficient_permissions'));

        $plan = $this->repository->find($this->context->workspace(), $id);

        return ApiResponse::success(
            new PlanResource($this->plans->archive($plan, $request->user())),
            __('messages.plan_archived'),
        );
    }

    /** DELETE /api/workspace/finance/plans/{id} (deletes an unused plan, archives one in use) */
    public function destroy(Request $request, string $id): JsonResponse
    {
        abort_unless($this->access->canManage($request->user()), 403, __('messages.insufficient_permissions'));

        $plan = $this->repository->find($this->context->workspace(), $id);
        $result = $this->plans->remove($plan, $request->user());

        return ApiResponse::success(
            ['plan' => new PlanResource($result['plan']), 'deleted' => $result['deleted']],
            __($result['deleted'] ? 'messages.plan_deleted' : 'messages.plan_archived'),
        );
    }
}
