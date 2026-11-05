<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ForfeitWalletRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
