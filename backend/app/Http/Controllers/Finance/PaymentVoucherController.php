<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListPaymentVouchersRequest;
use App\Http\Requests\Finance\VoidPaymentVoucherRequest;
use App\Http\Resources\PaymentVoucherResource;
use App\Repositories\Eloquent\PaymentVoucherRepository;
use App\Services\Finance\PaymentVoucherService;
use App\Services\InvoicePdfService;
use App\Support\ApiResponse;
use App\Support\Tenancy\CurrentWorkspaceContext;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Payment vouchers of the request's workspace. Routes sit behind the
 * `finance_v2` flag, the workspace context and a finance permission.
 */
class PaymentVoucherController extends Controller
{
    public function __construct(
        private readonly PaymentVoucherService $vouchers,
        private readonly PaymentVoucherRepository $repository,
        private readonly InvoicePdfService $pdf,
        private readonly CurrentWorkspaceContext $context,
    ) {}

    /** GET /api/workspace/finance/payment-vouchers */
    public function index(ListPaymentVouchersRequest $request): JsonResponse
    {
        $paginator = $this->repository->paginate($this->context->workspace(), $request->validated());

        return ApiResponse::success([
            'items' => PaymentVoucherResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /** GET /api/workspace/finance/payment-vouchers/{id}/pdf */
    public function pdf(string $id): StreamedResponse
    {
        return $this->pdf->streamPaymentVoucher($this->repository->find($this->context->workspace(), $id));
    }

    /** POST /api/workspace/finance/payment-vouchers/{id}/void */
    public function void(VoidPaymentVoucherRequest $request, string $id): JsonResponse
    {
        $voucher = $this->repository->find($this->context->workspace(), $id);

        $voided = $this->vouchers->void($voucher, $request->validated()['reason'], $request->user());

        return ApiResponse::success(new PaymentVoucherResource($voided), __('messages.payment_voucher_voided_ok'));
    }
}
