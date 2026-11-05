<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/** A credit note that cannot be issued. Renders itself as a 422 API error. */
class CreditNoteException extends RuntimeException
{
    public static function notIssued(): self
    {
        return new self(__('messages.credit_note_invoice_not_issued'));
    }

    public static function exceedsInvoice(string $remaining): self
    {
        return new self(__('messages.credit_note_exceeds_invoice', ['remaining' => $remaining]));
    }

    public static function heldByCheques(string $creditable): self
    {
        return new self(__('messages.credit_note_held_by_cheques', ['creditable' => $creditable]));
    }

    public static function invalidAmount(): self
    {
        return new self(__('messages.credit_note_amount_invalid'));
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 422);
    }
}
