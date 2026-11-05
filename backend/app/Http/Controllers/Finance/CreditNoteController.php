<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListCreditNotesRequest;
use App\Http\Requests\Finance\StoreCreditNoteRequest;
use App\Http\Resources\CreditNoteResource;
use App\Repositories\Eloquent\CreditNoteRepository;
use App\Services\Finance\CreditNoteService;
use App\Services\InvoicePdfService;
use App\Support\ApiResponse;
use App\Support\Tenancy\CurrentWorkspaceContext;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Credit notes of the request's workspace. Routes sit behind the `finance_v2`
 * flag, the workspace context and a finance permission, so the workspace is always set.
 */
class CreditNoteController extends Controller
{
    public function __construct(
        private readonly CreditNoteService $creditNotes,
        private readonly CreditNoteRepository $repository,
        private readonly InvoicePdfService $pdf,
        private readonly CurrentWorkspaceContext $context,
    ) {}

    /** GET /api/workspace/finance/credit-notes */
    public function index(ListCreditNotesRequest $request): JsonResponse
    {
        $paginator = $this->repository->paginate($this->context->workspace(), $request->validated());

        return ApiResponse::success([
            'items' => CreditNoteResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /** POST /api/workspace/invoices/{invoice}/credit-notes */
    public function store(StoreCreditNoteRequest $request, string $invoice): JsonResponse
    {
        $target = $this->repository->findInvoice($this->context->workspace(), $invoice);
        $data = $request->validated();

        $note = $this->creditNotes->issue($target, (string) $data['amount'], $data['reason'], $request->user());

        return ApiResponse::success(new CreditNoteResource($note), __('messages.credit_note_issued'), 201);
    }

    /** GET /api/workspace/finance/credit-notes/{id}/pdf */
    public function pdf(string $id): StreamedResponse
    {
        return $this->pdf->streamCreditNote($this->repository->find($this->context->workspace(), $id));
    }
}
