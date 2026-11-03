<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivateHourPurchaseRequest;
use App\Http\Requests\Admin\AdjustHourBalanceRequest;
use App\Http\Resources\HourPurchaseResource;
use App\Models\HourPackagePurchase;
use App\Models\User;
use App\Services\Hours\HourBalanceService;
use App\Services\Hours\HourPackageService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HourPurchaseController extends Controller
{
    public function __construct(
        private readonly HourPackageService $packages,
        private readonly HourBalanceService $balances,
    ) {}

    /** GET /api/admin/hour-purchases — the purchase queue plus per-status counts. */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->packages->paginatePurchases($request->only(['status', 'per_page']));

        return ApiResponse::success([
            ...HourPurchaseResource::collection($paginator)->response()->getData(true),
            'stats' => $this->packages->purchaseStats(),
        ]);
    }

    /** POST /api/admin/hour-purchases/{purchase}/activate */
    public function activate(ActivateHourPurchaseRequest $request, HourPackagePurchase $purchase): JsonResponse
    {
        $data = $request->validated();
        $paidAt = isset($data['paid_at']) ? Carbon::parse((string) $data['paid_at']) : null;

        $activated = $this->balances->activate(
            $purchase,
            ['payment_method' => $data['payment_method'], 'paid_at' => $paidAt],
            $request->file('receipt'),
            $request->user(),
        );

        return ApiResponse::success(new HourPurchaseResource($activated), __('messages.hour_purchase_activated'));
    }

    /** POST /api/admin/hour-purchases/{purchase}/cancel */
    public function cancel(Request $request, HourPackagePurchase $purchase): JsonResponse
    {
        $cancelled = $this->balances->cancel($purchase, $request->user());

        return ApiResponse::success(new HourPurchaseResource($cancelled), __('messages.hour_purchase_cancelled'));
    }

    /** POST /api/admin/hour-adjustments — manual balance correction with a required reason. */
    public function adjust(AdjustHourBalanceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::query()->findOrFail($data['user_id']);

        $this->balances->adjust($user, (int) $data['minutes'], $data['reason'], $request->user());

        return ApiResponse::success(
            ['user_id' => $user->id, 'balance_minutes' => $this->balances->balance($user)],
            __('messages.hour_adjustment_recorded'),
            201,
        );
    }
}
