<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use App\Enums\StockMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
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
            'type' => ['required', Rule::in([StockMovementType::Restock->value, StockMovementType::Adjustment->value])],
            // For a restock this is the amount to ADD; for an adjustment it is the
            // corrected ABSOLUTE count to set. Interpreted by `type` in the controller.
            'qty' => ['required', 'integer', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
