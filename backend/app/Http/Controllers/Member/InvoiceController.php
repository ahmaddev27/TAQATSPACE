<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * GET /api/member/invoices — the authed freelancer's invoices, paginated.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'month', 'per_page']);

        $paginator = $this->invoices->paginateForMember($request->user(), $filters);

        return ApiResponse::success([
            'invoices' => InvoiceResource::collection($paginator->items()),
            'meta' => $this->meta($paginator),
        ]);
    }

    /**
     * GET /api/member/invoices/{invoice} — a single invoice owned by the member.
     */
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        if (! $this->invoices->userCanAccess($invoice, $request->user())) {
            return ApiResponse::error('Invoice not found.', 404);
        }

        $invoice->load(['subscription.workspace', 'subscription.seat']);

        return ApiResponse::success(new InvoiceResource($invoice));
    }

    /**
     * @param  LengthAwarePaginator<int, Invoice>  $paginator
     * @return array<string, int>
     */
    private function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
