<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Enums\ChequeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChequeStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(ChequeStatus::class)],
            'collected_at' => ['nullable', 'date'],
        ];
    }
}
