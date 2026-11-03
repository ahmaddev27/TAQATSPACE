<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListUnbilledChargesRequest;
use App\Http\Requests\Finance\StoreUnbilledChargeRequest;
use App\Http\Resources\UnbilledChargeResource;
use App\Repositories\Eloquent\UnbilledChargeRepository;
use App\Services\Finance\CatalogAccess;
use App\Services\Finance\UnbilledChargeService;
use App\Support\ApiResponse;
use App\Support\Tenancy\CurrentWorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Unbilled charges of the request's workspace. Routes sit behind the
 * `finance_v2` flag and the workspace context, so the workspace is always set.
 */
class UnbilledChargeController extends Controller
{
    public function __construct(
        private readonly UnbilledChargeService $charges,
        private readonly UnbilledChargeRepository $repository,
        private readonly CatalogAccess $access,
        private readonly CurrentWorkspaceContext $context,
    ) {}

    /** GET /api/workspace/finance/unbilled-charges */
    public function index(ListUnbilledChargesRequest $request): JsonResponse
    {
        $paginator = $this->repository->paginate($this->context->workspace(), $request->validated());

        return ApiResponse::success([
            'items' => UnbilledChargeResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /** POST /api/workspace/finance/unbilled-charges */
    public function store(StoreUnbilledChargeRequest $request): JsonResponse
    {
        $charge = $this->charges->record($this->context->workspace(), $request->validated() + ['source' => 'manual'], $request->user());

        return ApiResponse::success(
            new UnbilledChargeResource($charge),
            __('messages.unbilled_charge_recorded'),
            $charge->wasRecentlyCreated ? 201 : 200,
        );
    }

    /** DELETE /api/workspace/finance/unbilled-charges/{id} (voids an open charge) */
    public function destroy(Request $request, string $id): JsonResponse
    {
        abort_unless($this->access->canManage($request->user()), 403, __('messages.insufficient_permissions'));

        $charge = $this->repository->find($this->context->workspace(), $id);
        $voided = $this->charges->void($charge, $request->user());

        return ApiResponse::success(new UnbilledChargeResource($voided), __('messages.unbilled_charge_voided'));
    }
}
