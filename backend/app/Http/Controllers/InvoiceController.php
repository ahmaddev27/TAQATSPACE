<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Invoice\MarkPaidRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\SubmitReceiptRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly InvoicePdfService $pdf,
    ) {}

    /**
     * GET /api/workspace/invoices — paginated, filterable, with summary tiles.
     */
    public function index(Request $request): JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 404);
        }

        $filters = $request->only(['status', 'month', 'per_page']);

        $paginator = $this->invoices->paginateForWorkspace($workspace, $filters);

        return ApiResponse::success([
            'invoices' => InvoiceResource::collection($paginator->items()),
            'summary' => $this->invoices->workspaceSummary($workspace),
            'meta' => $this->meta($paginator),
        ]);
    }

    /**
     * POST /api/workspace/invoices — manual invoice for a member.
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 404);
        }

        try {
            $invoice = $this->invoices->createManual($workspace, $request->validated());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $invoice->load(['subscription.member', 'subscription.seat', 'subscription.workspace']);

        return ApiResponse::success(new InvoiceResource($invoice), __('messages.invoice_created'), 201);
    }

    /**
     * GET /api/workspace/invoices/{invoice} — single invoice with member info.
     */
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            return ApiResponse::error(__('messages.invoice_not_found'), 404);
        }

        $detail = $this->invoices->findForWorkspace($workspace, $invoice->id);

        if ($detail === null) {
            return ApiResponse::error(__('messages.invoice_not_found'), 404);
        }

        return ApiResponse::success(new InvoiceResource($detail));
    }

    /**
     * PUT /api/workspace/invoices/{invoice}/pay — record a manual payment.
     */
    public function pay(MarkPaidRequest $request, Invoice $invoice): JsonResponse
    {
        if (! $this->ownsInvoice($request, $invoice)) {
            return ApiResponse::error(__('messages.invoice_not_found'), 404);
        }

        $paidAt = $request->validated()['paid_at'] ?? null;

        try {
            $updated = $this->invoices->markPaid(
                $invoice,
                $paidAt !== null ? Carbon::parse($paidAt) : null,
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $updated->load(['subscription.member', 'subscription.seat', 'subscription.workspace']);

        return ApiResponse::success(new InvoiceResource($updated), __('messages.invoice_marked_paid'));
    }

    /**
     * POST /api/workspace/invoices/{invoice}/receipt — the owner attaches a
     * payment receipt and records the invoice as paid in one step.
     */
    public function uploadReceipt(SubmitReceiptRequest $request, Invoice $invoice): JsonResponse
    {
        if (! $this->ownsInvoice($request, $invoice)) {
            return ApiResponse::error(__('messages.invoice_not_found'), 404);
        }

        try {
            $updated = $this->invoices->recordPaymentWithReceipt($invoice, $request->file('receipt'));
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $updated->load(['subscription.member', 'subscription.seat', 'subscription.workspace']);

        return ApiResponse::success(new InvoiceResource($updated), __('messages.invoice_marked_paid'));
    }

    /**
     * POST /api/workspace/invoices/{invoice}/remind — rate-limited reminder.
     */
    public function remind(Request $request, Invoice $invoice): JsonResponse
    {
        if (! $this->ownsInvoice($request, $invoice)) {
            return ApiResponse::error(__('messages.invoice_not_found'), 404);
        }

        try {
            $this->invoices->sendReminder($invoice);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 429);
        }

        return ApiResponse::message(__('messages.reminder_sent'));
    }

    /**
     * GET /api/invoices/{invoice}/pdf — owner or owning member only.
     */
    public function downloadPdf(Request $request, Invoice $invoice): StreamedResponse|JsonResponse
    {
        if (! $this->invoices->userCanAccess($invoice, $request->user())) {
            return ApiResponse::error(__('messages.invoice_not_found'), 404);
        }

        return $this->pdf->streamDownload($invoice);
    }

    private function ownsInvoice(Request $request, Invoice $invoice): bool
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            return false;
        }

        $invoice->loadMissing('subscription');

        return $invoice->subscription?->workspace_id === $workspace->id;
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Invoice>  $paginator
     * @return array<string, int>
     */
    private function meta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
