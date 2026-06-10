<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the manual mark-paid action: the payment date is required, and a
 * payment receipt (pdf/jpg/png, max 5MB) must be attached in the same step —
 * UNLESS the invoice already carries a receipt (e.g. one the member uploaded for
 * review). Authorization is enforced by the route middleware (auth:sanctum +
 * role.admin).
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
        /** @var \App\Models\Invoice|null $invoice */
        $invoice = $this->route('invoice');
        $alreadyHasReceipt = $invoice !== null && $invoice->receipt_path !== null;

        return [
            'paid_at' => ['required', 'date', 'before_or_equal:tomorrow'],
            'receipt' => [
                $alreadyHasReceipt ? 'nullable' : 'required',
                'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::RECEIPT_MAX_KILOBYTES,
            ],
        ];
    }
}
