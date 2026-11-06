<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveDepositRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
