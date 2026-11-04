<?php

declare(strict_types=1);

namespace App\Services\Demo\Steps;

use App\Enums\PaymentMethod;
use App\Enums\PosOrderSource;
use App\Enums\PosOrderStatus;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosPayment;
use App\Models\PosProduct;
use App\Services\Demo\DemoContext;
use App\Services\Demo\DemoSeedStep;

/** Café products and a handful of orders at different stages. */
class PosStep implements DemoSeedStep
{
    private const PRODUCTS = [
        ['قهوة', 'مشروبات', 5.0], ['شاي', 'مشروبات', 3.0], ['ماء', 'مشروبات', 1.5], ['كيك', 'مأكولات', 8.0],
    ];

    /** @var list<PosOrderStatus> */
    private const ORDER_STATUSES = [
        PosOrderStatus::Completed, PosOrderStatus::Completed, PosOrderStatus::New,
        PosOrderStatus::Preparing, PosOrderStatus::Cancelled, PosOrderStatus::Refunded,
    ];

    public function seed(DemoContext $context): void
    {
        $products = $this->seedProducts($context);

        foreach (self::ORDER_STATUSES as $index => $status) {
            $this->seedOrder($context, $products[$index % count($products)], $status, $index + 1);
        }
    }

    /** @return list<PosProduct> */
    private function seedProducts(DemoContext $context): array
    {
        return array_map(
            fn (array $row): PosProduct => PosProduct::query()->create([
                'workspace_id' => $context->workspace('ws1')->id,
                'name' => $row[0],
                'category' => $row[1],
                'price' => $row[2],
                'track_stock' => true,
                'stock_qty' => 50,
                'is_active' => true,
            ]),
            self::PRODUCTS,
        );
    }

    private function seedOrder(DemoContext $context, PosProduct $product, PosOrderStatus $status, int $number): void
    {
        $quantity = 2;
        $total = (float) $product->price * $quantity;
        $settled = in_array($status, [PosOrderStatus::Completed, PosOrderStatus::Refunded], true);
        $cashier = $context->user('staff_cashier');

        $order = PosOrder::query()->create([
            'workspace_id' => $product->workspace_id,
            'order_number' => sprintf('DEMO-%04d', $number),
            'source' => PosOrderSource::Cashier,
            'status' => $status,
            'member_id' => $context->user('freelancer1')->id,
            'cashier_id' => $cashier->id,
            'subtotal' => $total,
            'discount' => 0,
            'total' => $total,
            'paid_at' => $settled ? now()->subDay() : null,
            'refunded_at' => $status === PosOrderStatus::Refunded ? now() : null,
            'refunded_by' => $status === PosOrderStatus::Refunded ? $context->user('staff_finance')->id : null,
        ]);

        PosOrderItem::query()->create([
            'pos_order_id' => $order->id,
            'pos_product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => $product->price,
            'qty' => $quantity,
            'line_total' => $total,
        ]);

        if ($settled) {
            PosPayment::query()->create([
                'pos_order_id' => $order->id,
                'amount' => $total,
                'method' => PaymentMethod::Cash,
                'received_by' => $cashier->id,
                'paid_at' => now()->subDay(),
            ]);
        }
    }
}
