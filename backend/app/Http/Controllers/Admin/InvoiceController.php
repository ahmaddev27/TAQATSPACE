<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Exceptions\InvoiceLifecycleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MarkInvoicePaidRequest;
use App\Http\Requests\Admin\UploadReceiptRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\Admin\AdminInvoiceService;
use App\Support\ApiResponse;
use App\Support\Finance\PaymentResultMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly AdminInvoiceService $invoices,
    ) {}

    /**
     * GET /api/admin/invoices — filtered, paginated tracking list.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'search', 'date_from', 'date_to', 'per_page']);

        $paginator = $this->invoices->paginate($filters);

        return ApiResponse::success(InvoiceResource::collection($paginator)->response()->getData(true));
    }

    /**
     * PUT /api/admin/invoices/{invoice}/mark-paid — record a manual payment.
     */
    public function markPaid(MarkInvoicePaidRequest $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validated();
        $paidAt = $data['paid_at'] ?? null;

        try {
            $updated = $this->invoices->markPaid(
                $invoice,
                $paidAt !== null ? Carbon::parse((string) $paidAt) : null,
                $request->file('receipt'),
                isset($data['method']) ? PaymentMethod::from((string) $data['method']) : null,
            );
        } catch (InvoiceLifecycleException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(new InvoiceResource($updated), PaymentResultMessage::for($updated));
    }

    /**
     * PUT /api/admin/invoices/{invoice}/mark-unpaid — revert to pending.
     */
    public function markUnpaid(Request $request, Invoice $invoice): JsonResponse
    {
        $updated = $this->invoices->markUnpaid($invoice, $request->user());

        return ApiResponse::success(new InvoiceResource($updated), __('messages.invoice_reverted_pending'));
    }

    /**
     * POST /api/admin/invoices/{invoice}/receipt — upload a payment receipt.
     */
    public function uploadReceipt(UploadReceiptRequest $request, Invoice $invoice): JsonResponse
    {
        $paidAt = $request->validated()['paid_at'] ?? null;

        $updated = $this->invoices->attachReceipt(
            $invoice,
            $request->file('receipt'),
            $paidAt !== null ? Carbon::parse((string) $paidAt) : null,
        );

        return ApiResponse::success(new InvoiceResource($updated), PaymentResultMessage::for($updated, 'messages.receipt_uploaded'));
    }
}
