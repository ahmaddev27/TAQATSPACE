<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Enums\PaymentMethod;
use App\Enums\PosOrderSource;
use App\Enums\PosOrderStatus;
use App\Enums\StockMovementType;
use App\Enums\UserStatus;
use App\Models\PosOrder;
use App\Models\PosProduct;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\PosNewOrderNotification;
use App\Notifications\PosOrderStatusNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * The POS sales engine for a workspace. An order has two independent axes:
 *  - FULFILLMENT ({@see PosOrderStatus}: new -> preparing -> ready -> completed,
 *    plus cancelled/refunded), advanced through {@see setStatus}, and
 *  - PAYMENT (`paid_at`), settled by {@see pay} at any point in that flow.
 *
 * Stock is consumed only at PAYMENT (never on placement), and restored on
 * {@see refund}. All amounts are workspace-scoped and snapshotted onto the lines.
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
     * Create a NEW order from catalogue lines. Product name + price are
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

        $order = DB::transaction(function () use ($workspace, $source, $data, $cashier, $member, $lines, $subtotal, $discount): PosOrder {
            $order = PosOrder::query()->create([
                'workspace_id' => $workspace->id,
                'order_number' => $this->nextOrderNumber($workspace),
                'source' => $source->value,
                'status' => PosOrderStatus::New->value,
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

        // A freelancer placing an order remotely won't be seen unless staff are
        // watching the queue — alert the owner + active cashiers. A cashier's own
        // walk-in ring-up needs no alert.
        if ($source === PosOrderSource::Freelancer) {
            $this->notifyStaff($workspace, $order);
        }

        return $order;
    }

    /**
     * Settle an order: record the payment and consume stock for every tracked
     * line (re-checked under lock). Payment is decoupled from fulfillment — the
     * `status` column is left untouched. An order can only be paid once, and
     * never once cancelled or refunded.
     */
    public function pay(Workspace $workspace, PosOrder $order, PaymentMethod $method, User $actor): PosOrder
    {
        $this->ensureBelongs($workspace, $order);

        if ($order->paid_at !== null || in_array($order->status, [PosOrderStatus::Cancelled, PosOrderStatus::Refunded], true)) {
            throw new RuntimeException(__('messages.pos_order_not_payable'));
        }

        $paid = DB::transaction(function () use ($order, $method, $actor): PosOrder {
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

            $order->forceFill(['paid_at' => now()])->save();

            return $order->fresh(['items', 'payments']) ?? $order;
        });

        $this->notifyMember($order, 'paid');

        return $paid;
    }

    /**
     * Advance an order's FULFILLMENT status (preparing / ready / completed) and
     * notify the member. Payment is a separate axis and is not touched here. A
     * closed order (cancelled/refunded) can no longer move.
     */
    public function setStatus(Workspace $workspace, PosOrder $order, PosOrderStatus $to, User $actor): PosOrder
    {
        $this->ensureBelongs($workspace, $order);

        if (! in_array($to, [PosOrderStatus::Preparing, PosOrderStatus::Ready, PosOrderStatus::Completed], true)) {
            throw new RuntimeException(__('messages.pos_order_status_invalid'));
        }

        if (in_array($order->status, [PosOrderStatus::Cancelled, PosOrderStatus::Refunded], true)) {
            throw new RuntimeException(__('messages.pos_order_closed'));
        }

        $order->forceFill(['status' => $to->value])->save();

        $this->notifyMember($order, $to->value);

        return $order;
    }

    /**
     * At-a-glance POS figures for a workspace's dashboard section. `pending_orders`
     * counts OPEN orders (new/preparing/ready); `today_sales` sums orders paid
     * today, regardless of fulfillment status.
     *
     * @return array{today_sales: string, today_orders: int, pending_orders: int, low_stock: int}
     */
    public function summary(Workspace $workspace): array
    {
        $paidToday = PosOrder::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', now()->toDateString());

        return [
            'today_sales' => number_format((float) (clone $paidToday)->sum('total'), 2, '.', ''),
            'today_orders' => (clone $paidToday)->count(),
            'pending_orders' => PosOrder::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('status', $this->openStatuses())
                ->count(),
            'low_stock' => PosProduct::query()
                ->where('workspace_id', $workspace->id)
                ->where('is_active', true)
                ->where('track_stock', true)
                ->where('stock_qty', '<=', self::LOW_STOCK_THRESHOLD)
                ->count(),
        ];
    }

    /**
     * Void an order. Only an OPEN, still-UNPAID order can be cancelled (no stock
     * was consumed, so nothing to restore). Paid orders are reversed via
     * {@see refund} instead.
     */
    public function cancel(Workspace $workspace, PosOrder $order): PosOrder
    {
        $this->ensureBelongs($workspace, $order);

        if ($order->paid_at !== null || ! $order->status->isOpen()) {
            throw new RuntimeException(__('messages.pos_order_cannot_cancel'));
        }

        $order->forceFill(['status' => PosOrderStatus::Cancelled->value])->save();

        return $order;
    }

    /**
     * Reverse a paid order: restore stock for every tracked line, record a
     * negative (offsetting) payment, and mark the order refunded. Only a paid,
     * not-already-refunded order can be refunded.
     */
    public function refund(Workspace $workspace, PosOrder $order, User $actor): PosOrder
    {
        $this->ensureBelongs($workspace, $order);

        if ($order->paid_at === null || $order->status === PosOrderStatus::Refunded) {
            throw new RuntimeException(__('messages.pos_order_not_refundable'));
        }

        $refunded = DB::transaction(function () use ($order, $actor): PosOrder {
            $order->loadMissing(['items', 'payments']);

            foreach ($order->items as $item) {
                $product = $item->pos_product_id !== null
                    ? PosProduct::query()->find($item->pos_product_id)
                    : null;

                if ($product !== null && $product->track_stock) {
                    $this->products->adjustStock(
                        $order->workspace,
                        $product,
                        StockMovementType::Restock,
                        $item->qty,
                        'refund '.$order->order_number,
                        $actor,
                    );
                }
            }

            $method = $order->payments->first()?->method ?? PaymentMethod::Cash;

            $order->payments()->create([
                'amount' => -$order->total,
                'method' => $method->value,
                'received_by' => $actor->id,
                'paid_at' => now(),
            ]);

            $order->forceFill([
                'status' => PosOrderStatus::Refunded->value,
                'refunded_at' => now(),
                'refunded_by' => $actor->id,
            ])->save();

            return $order->fresh(['items', 'payments']) ?? $order;
        });

        $this->notifyMember($order, 'refunded');

        return $refunded;
    }

    /**
     * Send the order's member (if any) the in-app lifecycle notification. Walk-in
     * orders have no member and notify no one.
     */
    private function notifyMember(PosOrder $order, string $event): void
    {
        if ($order->member_id === null) {
            return;
        }

        $order->loadMissing('member');
        $order->member?->notify(new PosOrderStatusNotification($order, $event));
    }

    /** Alert the workspace's POS staff (owner + active cashiers) of a new order. */
    private function notifyStaff(Workspace $workspace, PosOrder $order): void
    {
        $workspace->loadMissing('owner');

        $recipients = collect([$workspace->owner])
            ->merge($workspace->cashiers()->where('status', UserStatus::Active->value)->get())
            ->filter();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new PosNewOrderNotification($order->loadMissing('member')));
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
     * The fulfillment statuses that keep an order in the active queue.
     *
     * @return array<int, string>
     */
    private function openStatuses(): array
    {
        return [
            PosOrderStatus::New->value,
            PosOrderStatus::Preparing->value,
            PosOrderStatus::Ready->value,
        ];
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
