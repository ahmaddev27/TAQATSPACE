<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Which part of the product wrote a receipt. A receipt without a source was
 * recorded by the workspace itself (the receipts page, a cheque) or copied
 * from a legacy payment; only the workspace may void those.
 */
enum ReceiptSource: string
{
    use HasValues;

    /** Written by a pay action on the invoice: mark paid, proof upload, approve, partial payment, admin mark paid. */
    case InvoicePayment = 'invoice_payment';
}
