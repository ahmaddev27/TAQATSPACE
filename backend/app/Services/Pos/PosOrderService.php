<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Enums\PaymentMethod;
use App\Enums\PosOrderSource;
use App\Enums\PosOrderStatus;
use App\Enums\StockMovementType;
use App\Models\PosOrder;
use App\Models\PosProduct;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The POS sales engine for a workspace: build an order from catalogue lines,
 * settle it with a payment, or void it. Stock is consumed only when an order is
 * PAID (never on placement), so a pending order never holds inventory hostage.
 * All amounts are workspace-scoped and snapshotted onto the order lines.
 */
class PosOrderService
{
    /** Stock at or below this (for a tracked product) counts as "low". */
    private const LOW_STOCK_THRESHOLD = 5;

    public function __construct(
        private readonly PosProductService $products,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PosOrder>
     */
    public function paginate(Workspace $workspace, array $filters): LengthAwarePaginator
    {
        return $this->query($workspace, $filters)
            ->with(['items', 'member:id,name'])
            ->latest()
            ->paginate(max(1, min((int) ($filters['per_page'] ?? 20), 100)))
            ->withQueryString();
    }

    /**
     * Create a PENDING order from catalogue lines. Product name + price are
     * snapshotted; stock is validated but NOT yet consumed.
     *
     * @param  array{items: array<int, array{product_id: string, qty: int}>, discount?: float|int|null, customer_name?: string|null, note?: string|null}  $data
     */
    public function create(
        Workspace $workspace,
        PosOrderSource $source,
        array $data,
        ?User $cashier,
        ?User $member,
    ): PosOrder {
        $lines = $this->buildLines($workspace, $data['items']);
        $subtotal = array_sum(array_column($lines, 'line_total'));
        $discount = min(max((float) ($data['discount'] ?? 0), 0), $subtotal);

        return DB::transaction(function () use ($workspace, $source, $data, $cashier, $member, $lines, $subtotal, $discount): PosOrder {
            $order = PosOrder::query()->create([
                'workspace_id' => $workspace->id,
                'order_number' => $this->nextOrderNumber($workspace),
                'source' => $source->value,
                'status' => PosOrderStatus::Pending->value,
                'member_id' => $member?->id,
                'customer_name' => $data['customer_name'] ?? null,
                'cashier_id' => $cashier?->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $subtotal - $discount,
                'note' => $data['note'] ?? null,
            ]);

            $order->items()->createMany($lines);

            return $order->load('items');
        });
    }

    /**
     * Settle a pending order: record the payment, consume stock for every tracked
     * line (re-checked under lock), and mark it paid.
     */
    public function pay(Workspace $workspace, PosOrder $order, PaymentMethod $method, User $actor): PosOrder
    {
        $this->ensureBelongs($workspace, $order);

        if ($order->status !== PosOrderStatus::Pending) {
            throw new RuntimeException(__('messages.pos_order_not_pending'));
        }

        return DB::transaction(function () use ($order, $method, $actor): PosOrder {
            foreach ($order->items as $item) {
                $product = $item->pos_product_id !== null
                    ? PosProduct::query()->find($item->pos_product_id)
                    : null;

                if ($product !== null && $product->track_stock) {
                    // Reuses the locked, journalled decrement — throws if another
                    // sale drained the stock between placement and payment.
                    $this->products->adjustStock(
                        $order->workspace,
                        $product,
                        StockMovementType::Sale,
                        -$item->qty,
                        $order->order_number,
                        $actor,
                    );
                }
            }

            $order->payments()->create([
                'amount' => $order->total,
                'method' => $method->value,
                'received_by' => $actor->id,
                'paid_at' => now(),
            ]);

            $order->forceFill([
                'status' => PosOrderStatus::Paid->value,
                'paid_at' => now(),
            ])->save();

            return $order->fresh(['items', 'payments']) ?? $order;
        });
    }

    /**
     * At-a-glance POS figures for a workspace's dashboard section.
     *
     * @return array{today_sales: string, today_orders: int, pending_orders: int, low_stock: int}
     */
    public function summary(Workspace $workspace): array
    {
        $paidToday = PosOrder::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', PosOrderStatus::Paid->value)
            ->whereDate('paid_at', now()->toDateString());

        return [
            'today_sales' => number_format((float) (clone $paidToday)->sum('total'), 2, '.', ''),
            'today_orders' => (clone $paidToday)->count(),
            'pending_orders' => PosOrder::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', PosOrderStatus::Pending->value)
                ->count(),
            'low_stock' => PosProduct::query()
                ->where('workspace_id', $workspace->id)
                ->where('is_active', true)
                ->where('track_stock', true)
                ->where('stock_qty', '<=', self::LOW_STOCK_THRESHOLD)
                ->count(),
        ];
    }

    /** Void a pending order (no stock was consumed, so nothing to restore). */
    public function cancel(Workspace $workspace, PosOrder $order): PosOrder
    {
        $this->ensureBelongs($workspace, $order);

        if ($order->status !== PosOrderStatus::Pending) {
            throw new RuntimeException(__('messages.pos_order_not_pending'));
        }

        $order->forceFill(['status' => PosOrderStatus::Cancelled->value])->save();

        return $order;
    }

    /**
     * Resolve + validate the order lines against the workspace catalogue.
     *
     * @param  array<int, array{product_id: string, qty: int}>  $items
     * @return array<int, array{pos_product_id: string, name: string, unit_price: string, qty: int, line_total: float}>
     */
    private function buildLines(Workspace $workspace, array $items): array
    {
        if ($items === []) {
            throw new RuntimeException(__('messages.pos_order_empty'));
        }

        $products = PosProduct::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('id', array_column($items, 'product_id'))
            ->get()
            ->keyBy('id');

        $lines = [];

        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            $qty = (int) $item['qty'];

            if ($product === null || ! $product->is_active) {
                throw new RuntimeException(__('messages.pos_product_not_found'));
            }

            if (! $product->isSellable($qty)) {
                throw new RuntimeException(__('messages.pos_stock_insufficient'));
            }

            $lines[] = [
                'pos_product_id' => $product->id,
                'name' => $product->name,
                'unit_price' => $product->price,
                'qty' => $qty,
                'line_total' => round((float) $product->price * $qty, 2),
            ];
        }

        return $lines;
    }

    /** Sequential per-workspace order number `POS-YYYY-0001` (race-safe). */
    private function nextOrderNumber(Workspace $workspace): string
    {
        $count = PosOrder::query()
            ->where('workspace_id', $workspace->id)
            ->lockForUpdate()
            ->count();

        return sprintf('POS-%s-%04d', now()->format('Y'), $count + 1);
    }

    private function ensureBelongs(Workspace $workspace, PosOrder $order): void
    {
        if ((string) $order->workspace_id !== (string) $workspace->id) {
            abort(404, __('messages.pos_order_not_found'));
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PosOrder>
     */
    private function query(Workspace $workspace, array $filters): Builder
    {
        $query = PosOrder::query()->where('workspace_id', $workspace->id);

        if (! empty($filters['status']) && PosOrderStatus::tryFrom((string) $filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }
}
