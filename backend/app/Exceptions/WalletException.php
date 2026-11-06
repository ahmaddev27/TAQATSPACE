<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/** A wallet or voucher operation that cannot be applied. Renders itself as a 422 (409 for a state conflict). */
class WalletException extends RuntimeException
{
    private function __construct(string $message, private readonly int $status = 422)
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

    public static function unknownCustomer(): self
    {
        return new self(__('messages.wallet_unknown_customer'));
    }

    public static function requestKeyReused(): self
    {
        return new self(__('messages.wallet_request_key_reused'), 409);
    }

    public static function voucherVoided(): self
    {
        return new self(__('messages.payment_voucher_voided'), 409);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->status);
    }
}
