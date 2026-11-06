<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A wallet or voucher operation that cannot be applied. Renders itself as a
 * 422, or a 409 with a stable `code` when the request conflicts with the
 * wallet's state.
 */
class WalletException extends RuntimeException
{
    private function __construct(string $message, private readonly int $status = 422, private readonly ?string $errorCode = null)
    {
        parent::__construct($message);
    }

    public static function insufficient(string $balance): self
    {
        return new self(__('messages.wallet_insufficient', ['balance' => $balance]));
    }

    public static function invalidAmount(): self
    {
        return new self(__('messages.wallet_amount_invalid'));
    }

    public static function depositMethod(): self
    {
        return new self(__('messages.wallet_deposit_method_invalid'));
    }

    public static function unknownCustomer(): self
    {
        return new self(__('messages.wallet_unknown_customer'));
    }

    public static function requestKeyReused(): self
    {
        return new self(__('messages.wallet_request_key_reused'), 409, 'wallet_request_key_reused');
    }

    public static function voucherVoided(): self
    {
        return new self(__('messages.payment_voucher_voided'), 409, 'payment_voucher_voided');
    }

    public static function notReversible(): self
    {
        return new self(__('messages.wallet_entry_not_reversible'));
    }

    public static function alreadyReversed(): self
    {
        return new self(__('messages.wallet_entry_already_reversed'), 409, 'wallet_entry_already_reversed');
    }

    /**
     * Money an invoice moved to the wallet has to come back off it, and the
     * wallet no longer holds it: it was paid out or used. The owner voids
     * those documents first, then retries.
     *
     * @param  list<string>  $documents  numbers of the vouchers and wallet settlements that took money out
     */
    public static function refundSpent(string $amount, string $balance, array $documents): self
    {
        $message = $documents === []
            ? __('messages.wallet_refund_spent_unnamed', ['amount' => $amount, 'balance' => $balance])
            : __('messages.wallet_refund_spent', ['amount' => $amount, 'balance' => $balance, 'documents' => implode(', ', $documents)]);

        return new self($message, 409, 'wallet_refund_spent');
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->status, code: $this->errorCode);
    }
}
