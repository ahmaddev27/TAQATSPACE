<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use App\Enums\PosOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in([
                PosOrderStatus::Preparing->value,
                PosOrderStatus::Ready->value,
                PosOrderStatus::Completed->value,
            ])],
        ];
    }
}
