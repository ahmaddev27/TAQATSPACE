<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Owner records a partial (or final) manual payment against an invoice. The
 * amount is added to the running total; ownership is verified in the controller.
 */
class PartialPaymentRequest extends FormRequest
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
            'paid_at' => ['nullable', 'date', 'before_or_equal:tomorrow'],
        ];
    }
}
