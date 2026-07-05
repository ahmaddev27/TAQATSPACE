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
            'qty_change' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
