<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the manual mark-paid action: an optional payment timestamp and an
 * optional payment receipt attached in the same step (pdf/jpg/png, max 5MB —
 * the same rules as a standalone receipt upload). Authorization is enforced by
 * the route middleware (auth:sanctum + role.admin).
 */
class MarkInvoicePaidRequest extends FormRequest
{
    private const RECEIPT_MAX_KILOBYTES = 5120;

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
            'paid_at' => ['nullable', 'date', 'before_or_equal:tomorrow'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::RECEIPT_MAX_KILOBYTES],
        ];
    }
}
