<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnDepositRequest extends FormRequest
{
    use WalletRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->walletAmountRules(),
            'method' => ['required', Rule::in(PaymentMethod::manualValues())],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
            // A voucher records money already handed over, so it cannot be dated ahead.
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
