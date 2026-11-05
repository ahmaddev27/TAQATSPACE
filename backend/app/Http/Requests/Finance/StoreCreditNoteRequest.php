<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreCreditNoteRequest extends FormRequest
{
    /** `credit_notes.amount` is DECIMAL(12,2) and `invoices.amount` is DECIMAL(8,2). */
    private const MAX_AMOUNT = 999999.99;

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
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.self::MAX_AMOUNT, 'decimal:0,2'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
