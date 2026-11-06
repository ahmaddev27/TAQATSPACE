<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Optional payment timestamp; defaults to now() in the service.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            // Finance v2 only: how the money came in. A cheque is recorded as a receipt, never marked paid.
            'method' => ['nullable', Rule::in([PaymentMethod::Cash->value, PaymentMethod::Card->value, PaymentMethod::Transfer->value])],
        ];
    }
}
