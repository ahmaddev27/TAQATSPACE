<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Enums\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a new workspace expense submitted by the owner. Ownership is
 * enforced in the controller/service (the expense is attached to the owner's
 * own workspace), so no workspace_id is accepted from the client.
 */
class StoreExpenseRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(ExpenseCategory::values())],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'spent_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
