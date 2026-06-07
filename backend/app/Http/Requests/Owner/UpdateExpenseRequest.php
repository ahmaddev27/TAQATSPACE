<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Enums\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an edit to an existing workspace expense. Every field is optional
 * (`sometimes`) so the owner may patch a subset.
 */
class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', Rule::in(ExpenseCategory::values())],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99'],
            'spent_on' => ['sometimes', 'required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
