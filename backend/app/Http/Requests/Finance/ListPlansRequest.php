<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Enums\PricingMethod;
use App\Services\Finance\PlanAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPlansRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(PlanAccess::class)->canView($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'pricing_method' => ['nullable', Rule::enum(PricingMethod::class)],
            'archived' => ['nullable', Rule::in(['exclude', 'include', 'only'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
