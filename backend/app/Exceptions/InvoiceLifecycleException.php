<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A rejected invoice lifecycle operation. Renders itself as the standard API
 * error envelope: 409 when the invoice is frozen, 422 for a bad request.
 */
class InvoiceLifecycleException extends RuntimeException
{
    private function __construct(string $message, private readonly int $status)
    {
        parent::__construct($message);
    }

    public static function immutable(): self
    {
        return new self(__('messages.invoice_immutable'), 409);
    }

    public static function notDraft(): self
    {
        return new self(__('messages.invoice_not_draft'), 409);
    }

    public static function notIssued(): self
    {
        return new self(__('messages.invoice_not_issued'), 409);
    }

    public static function noLines(): self
    {
        return new self(__('messages.invoice_no_lines'), 422);
    }

    public static function totalTooLarge(): self
    {
        return new self(__('messages.invoice_total_too_large'), 422);
    }

    public static function unknownCustomer(): self
    {
        return new self(__('messages.unbilled_charge_unknown_customer'), 422);
    }

    public static function chargeUnavailable(): self
    {
        return new self(__('messages.invoice_charge_unavailable'), 422);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->status);
    }
}
