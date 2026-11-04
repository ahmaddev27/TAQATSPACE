<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\ChequeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\AllocateReceiptRequest;
use App\Http\Requests\Finance\ChequeStatusRequest;
use App\Http\Requests\Finance\ListReceiptsRequest;
use App\Http\Requests\Finance\StoreReceiptRequest;
use App\Http\Requests\Finance\VoidReceiptRequest;
use App\Http\Resources\ReceiptResource;
use App\Models\Receipt;
use App\Repositories\Eloquent\ReceiptRepository;
use App\Services\Finance\ReceiptService;
use App\Support\ApiResponse;
use App\Support\Tenancy\CurrentWorkspaceContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Receipts of the request's workspace. Routes sit behind the `finance_v2` flag,
 * the workspace context and a finance permission, so the workspace is always set.
 */
class ReceiptController extends Controller
{
    public function __construct(
        private readonly ReceiptService $receipts,
        private readonly ReceiptRepository $repository,
        private readonly CurrentWorkspaceContext $context,
    ) {}

    /** GET /api/workspace/finance/receipts */
    public function index(ListReceiptsRequest $request): JsonResponse
    {
        $paginator = $this->repository->paginate($this->context->workspace(), $request->validated());

        return ApiResponse::success([
            'items' => ReceiptResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /** GET /api/workspace/finance/receipts/{id} */
    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new ReceiptResource($this->repository->find($this->context->workspace(), $id)));
    }

    /** POST /api/workspace/finance/receipts */
    public function store(StoreReceiptRequest $request): JsonResponse
    {
        $receipt = $this->receipts->create(
            $this->context->workspace(),
            $request->safe()->except(['receipt']),
            $request->user(),
            $request->file('receipt'),
        );

        return ApiResponse::success(new ReceiptResource($receipt), __('messages.receipt_created'), 201);
    }

    /** POST /api/workspace/finance/receipts/{id}/allocations */
    public function allocate(AllocateReceiptRequest $request, string $id): JsonResponse
    {
        $receipt = $this->repository->find($this->context->workspace(), $id);

        $allocated = $this->receipts->allocate($receipt, $request->validated()['allocations'], $request->user());

        return ApiResponse::success(new ReceiptResource($allocated), __('messages.receipt_allocated'));
    }

    /** PUT /api/workspace/finance/receipts/{id}/cheque-status */
    public function chequeStatus(ChequeStatusRequest $request, string $id): JsonResponse
    {
        $receipt = $this->repository->find($this->context->workspace(), $id);
        $data = $request->validated();

        $updated = $this->receipts->changeChequeStatus(
            $receipt,
            ChequeStatus::from($data['status']),
            $request->user(),
            isset($data['collected_at']) ? Carbon::parse($data['collected_at']) : null,
        );

        return ApiResponse::success(new ReceiptResource($updated), __('messages.receipt_cheque_updated'));
    }

    /** POST /api/workspace/finance/receipts/{id}/void */
    public function void(VoidReceiptRequest $request, string $id): JsonResponse
    {
        $receipt = $this->repository->find($this->context->workspace(), $id);

        $voided = $this->receipts->void($receipt, $request->validated()['reason'], $request->user());

        return ApiResponse::success(new ReceiptResource($voided), __('messages.receipt_voided_ok'));
    }

    /** GET /api/finance/receipts/{receipt}/file (signed, short-lived; the URL is the credential) */
    public function file(Receipt $receipt): StreamedResponse
    {
        abort_if($receipt->receipt_path === null || $receipt->receipt_disk === null, 404);

        return Storage::disk($receipt->receipt_disk)->response($receipt->receipt_path);
    }
}
