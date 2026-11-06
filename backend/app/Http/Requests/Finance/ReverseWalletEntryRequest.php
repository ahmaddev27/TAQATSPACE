<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ReverseWalletEntryRequest extends FormRequest
{
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
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            // A client-made key (a UUID is fine) that turns a repeated submit into a no-op.
            'request_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
