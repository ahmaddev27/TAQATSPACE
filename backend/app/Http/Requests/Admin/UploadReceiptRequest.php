<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a payment-receipt upload (pdf/jpg/png, max 5MB). Uploading a receipt
 * is the admin's proof of payment, so the invoice is marked paid by the handler;
 * an optional `paid_at` backdates that payment. Authorization is enforced by the
 * route middleware (auth:sanctum + role.admin).
 */
class UploadReceiptRequest extends FormRequest
{
    private const MAX_KILOBYTES = 5120;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_KILOBYTES],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
