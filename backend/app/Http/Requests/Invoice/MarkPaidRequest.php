<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use App\Http\Requests\Concerns\TakesPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

class MarkPaidRequest extends FormRequest
{
    use TakesPaymentMethod;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Optional payment timestamp; defaults to now() in the service. With
     * finance v2 on, also an optional method (cash unless given).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            ...$this->paymentMethodRules(),
        ];
    }
}
