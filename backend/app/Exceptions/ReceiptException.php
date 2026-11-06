<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A rejected receipt operation. Renders itself as the standard API error
 * envelope: 409 for a state conflict, 422 for a request that cannot be applied.
 */
class ReceiptException extends RuntimeException
{
    private function __construct(string $message, private readonly int $status)
    {
        parent::__construct($message);
    }

    public static function exceedsReceipt(): self
    {
        return new self(__('messages.receipt_allocation_exceeds_receipt'), 422);
    }

    public static function exceedsBalance(string $remaining): self
    {
        return new self(__('messages.invoice_payment_exceeds_remaining', ['remaining' => $remaining]), 422);
    }

    /** Everything left on the invoice is already covered by pending cheques. */
    public static function nothingOpen(): self
    {
        return new self(__('messages.invoice_nothing_open_to_pay'), 422);
    }

    public static function invoiceMismatch(): self
    {
        return new self(__('messages.receipt_invoice_mismatch'), 422);
    }

    public static function unknownCustomer(): self
    {
        return new self(__('messages.unbilled_charge_unknown_customer'), 422);
    }

    public static function notCheque(): self
    {
        return new self(__('messages.receipt_not_cheque'), 422);
    }

    public static function invalidChequeTransition(): self
    {
        return new self(__('messages.receipt_cheque_transition_invalid'), 409);
    }

    public static function voided(): self
    {
        return new self(__('messages.receipt_voided'), 409);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->status);
    }
}
