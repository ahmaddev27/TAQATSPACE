<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The owner records a payment against an invoice: an amount (partial or full), a
 * required receipt as proof, and an optional payment date. Ownership of the
 * invoice is verified in the controller.
 */
class RecordPaymentRequest extends FormRequest
{
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
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:tomorrow'],
        ];
    }
}
