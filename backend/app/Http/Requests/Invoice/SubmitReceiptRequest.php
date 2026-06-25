<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A member uploads proof of payment for an invoice. Ownership of the invoice is
 * verified in the controller against the authenticated member.
 */
class SubmitReceiptRequest extends FormRequest
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
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            // Optional payment date when an owner records the payment; the member
            // submit flow omits it (the service defaults to now()).
            'paid_at' => ['nullable', 'date', 'before_or_equal:tomorrow'],
        ];
    }
}
