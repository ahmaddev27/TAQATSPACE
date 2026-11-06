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
            // Money in hand only: a cheque deposit would count, and could be paid out, before it clears.
            'method' => ['required', Rule::in(PaymentMethod::inHandValues())],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
