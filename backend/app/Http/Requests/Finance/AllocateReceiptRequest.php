<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class AllocateReceiptRequest extends FormRequest
{
    use AllocationRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['allocations' => ['required', 'array', 'min:1', 'max:50'], ...$this->allocationRules()];
    }
}
