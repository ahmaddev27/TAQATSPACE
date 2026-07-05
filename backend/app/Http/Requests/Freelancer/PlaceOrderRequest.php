<?php

declare(strict_types=1);

namespace App\Http\Requests\Freelancer;

use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
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
            'workspace_id' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:10000'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
