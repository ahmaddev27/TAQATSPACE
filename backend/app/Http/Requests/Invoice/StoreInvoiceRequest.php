<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Manual invoice creation by an owner for one of their members.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'member_id' => ['required', 'uuid', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
