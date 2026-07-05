<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * The reason a product's stock changed, recorded on each `pos_stock_movements`
 * row.
 */
enum StockMovementType: string
{
    use HasValues;

    /** Staff added stock (a delivery / purchase). */
    case Restock = 'restock';

    /** Staff corrected the count (breakage, recount) — may be + or -. */
    case Adjustment = 'adjustment';

    /** An order consumed stock (automatic, on sale). */
    case Sale = 'sale';
}
