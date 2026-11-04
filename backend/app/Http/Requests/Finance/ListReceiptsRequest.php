<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Enums\ChequeStatus;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListReceiptsRequest extends FormRequest
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
            'customer_id' => ['nullable', 'uuid'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'cheque_status' => ['nullable', Rule::enum(ChequeStatus::class)],
            'state' => ['nullable', Rule::in(['active', 'voided'])],
            'sort' => ['nullable', Rule::in(['received_at', 'collected_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
