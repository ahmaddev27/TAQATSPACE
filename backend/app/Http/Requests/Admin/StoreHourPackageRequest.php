<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or update a catalog package. Authorization is enforced by the route
 * middleware (auth:sanctum + role.admin + can.permission:manage_billing).
 */
class StoreHourPackageRequest extends FormRequest
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
        $package = $this->route('hour_package');
        $required = $package === null ? 'required' : 'sometimes';

        return [
            'code' => [$required, 'string', 'max:50', Rule::unique('hour_packages', 'code')->ignore($package?->getKey())],
            'name_ar' => [$required, 'string', 'max:120'],
            'name_en' => [$required, 'string', 'max:120'],
            'hours' => [$required, 'integer', 'min:1', 'max:100000'],
            'price' => [$required, 'numeric', 'min:0', 'max:99999999'],
            'currency' => [$required, 'string', 'size:3'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
