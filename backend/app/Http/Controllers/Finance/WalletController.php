<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ForfeitWalletRequest;
use App\Http\Requests\Finance\ListWalletsRequest;
use App\Http\Requests\Finance\ReceiveDepositRequest;
use App\Http\Requests\Finance\ReturnDepositRequest;
use App\Http\Requests\Finance\SettleWalletRequest;
use App\Http\Resources\PaymentVoucherResource;
use App\Http\Resources\ReceiptResource;
use App\Http\Resources\WalletEntryResource;
use App\Repositories\Eloquent\WalletRepository;
use App\Services\Finance\WalletService;
use App\Support\ApiResponse;
use App\Support\Tenancy\CurrentWorkspaceContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Customer wallets of the request's workspace. Routes sit behind the
 * `finance_v2` flag, the workspace context and a finance permission, so the
 * workspace is always set. Money is in the workspace's currency.
 */
class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly WalletRepository $repository,
        private readonly CurrentWorkspaceContext $context,
    ) {}

    /** GET /api/workspace/finance/wallet */
    public function index(ListWalletsRequest $request): JsonResponse
    {
        $workspace = $this->context->workspace();
        $paginator = $this->repository->paginateBalances($workspace, $request->validated());

        return ApiResponse::success([
            'currency' => $workspace->currency,
            'items' => array_map(static fn ($row): array => [
                'customer' => ['id' => $row->customer_id, 'name' => $row->customer_name],
                'balance' => number_format((float) $row->balance, 2, '.', ''),
                'last_entry_at' => $row->last_entry_at,
            ], $paginator->items()),
            'meta' => $this->meta($paginator),
        ]);
    }

    /** GET /api/workspace/finance/wallet/{customer} */
    public function show(Request $request, string $customer): JsonResponse
    {
        $workspace = $this->context->workspace();
        $user = $this->repository->customer($workspace, $customer);
        $entries = $this->repository->paginateEntries($workspace, $user->id, $request->integer('per_page', 50));

        return ApiResponse::success([
            'currency' => $workspace->currency,
            'customer' => ['id' => $user->id, 'name' => $user->name],
            'balance' => $this->wallets->balance($workspace, $user->id),
            'items' => WalletEntryResource::collection($entries->items()),
            'meta' => $this->meta($entries),
        ]);
    }

    /** POST /api/workspace/finance/wallet/{customer}/deposits */
    public function deposit(ReceiveDepositRequest $request, string $customer): JsonResponse
    {
        $data = $request->validated();

        $entry = $this->wallets->receiveDeposit(
            $this->context->workspace(),
            $customer,
            (string) $data['amount'],
            PaymentMethod::from($data['method']),
            $data['reason'] ?? null,
            $data['request_key'] ?? null,
            $request->user(),
        );

        return ApiResponse::success(new WalletEntryResource($entry), __('messages.wallet_deposit_received'), 201);
    }

    /** POST /api/workspace/finance/wallet/{customer}/returns */
    public function return(ReturnDepositRequest $request, string $customer): JsonResponse
    {
        $data = $request->validated();

        $voucher = $this->wallets->returnDeposit(
            $this->context->workspace(),
            $customer,
            (string) $data['amount'],
            PaymentMethod::from($data['method']),
            $data['reason'],
            $data['reference'] ?? null,
            isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : null,
            $data['request_key'] ?? null,
            $request->user(),
        );

        return ApiResponse::success(new PaymentVoucherResource($voucher), __('messages.wallet_deposit_returned'), 201);
    }

    /** POST /api/workspace/finance/wallet/{customer}/settlements */
    public function settle(SettleWalletRequest $request, string $customer): JsonResponse
    {
        $data = $request->validated();

        $receipt = $this->wallets->settleAgainstInvoice(
            $this->context->workspace(),
            $customer,
            $data['invoice_id'],
            (string) $data['amount'],
            $data['request_key'] ?? null,
            $request->user(),
        );

        return ApiResponse::success(new ReceiptResource($receipt), __('messages.wallet_settled'), 201);
    }

    /** POST /api/workspace/finance/wallet/{customer}/forfeits */
    public function forfeit(ForfeitWalletRequest $request, string $customer): JsonResponse
    {
        $data = $request->validated();

        $entry = $this->wallets->forfeit(
            $this->context->workspace(),
            $customer,
            (string) $data['amount'],
            $data['reason'],
            $data['request_key'] ?? null,
            $request->user(),
        );

        return ApiResponse::success(new WalletEntryResource($entry), __('messages.wallet_forfeited'), 201);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
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
