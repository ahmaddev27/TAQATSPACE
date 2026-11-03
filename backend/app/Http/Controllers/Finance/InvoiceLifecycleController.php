<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreInvoiceDraftRequest;
use App\Http\Requests\Finance\UpdateInvoiceDraftRequest;
use App\Http\Requests\Finance\VoidInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\Finance\InvoiceLifecycleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Draft -> issued -> void for the owner's workspace. Routes sit behind the
 * `finance_v2` flag; issue and void additionally need a recent 2FA check.
 */
class InvoiceLifecycleController extends Controller
{
    public function __construct(private readonly InvoiceLifecycleService $lifecycle) {}

    /** POST /api/workspace/invoices/drafts */
    public function store(StoreInvoiceDraftRequest $request): JsonResponse
    {
        $invoice = $this->lifecycle->createDraft($request->user()->workspace, $request->validated(), $request->user());

        return ApiResponse::success(new InvoiceResource($invoice), __('messages.invoice_draft_created'), 201);
    }

    /** PUT /api/workspace/invoices/{invoice}/draft */
    public function update(UpdateInvoiceDraftRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        $updated = $this->lifecycle->updateDraft($invoice, $request->validated(), $request->user());

        return ApiResponse::success(new InvoiceResource($updated), __('messages.invoice_draft_updated'));
    }

    /** POST /api/workspace/invoices/{invoice}/issue */
    public function issue(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        $issued = $this->lifecycle->issue($invoice, $request->user());

        return ApiResponse::success(new InvoiceResource($issued), __('messages.invoice_issued'));
    }

    /** POST /api/workspace/invoices/{invoice}/void */
    public function void(VoidInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        $voided = $this->lifecycle->void($invoice, $request->validated()['reason'], $request->user());

        return ApiResponse::success(new InvoiceResource($voided), __('messages.invoice_voided'));
    }

    private function authorizeInvoice(Request $request, Invoice $invoice): void
    {
        $workspace = $request->user()->workspace;

        abort_unless($workspace !== null && $invoice->belongsToWorkspace($workspace->id), 404, __('messages.invoice_not_found'));
    }
}
