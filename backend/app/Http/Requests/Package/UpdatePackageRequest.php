<?php

declare(strict_types=1);

namespace App\Http\Requests\Package;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePackageRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'speed_mbps' => ['sometimes', 'integer', 'min:1'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'data_limit_gb' => ['nullable', 'integer', 'min:1'],
            'is_unlimited' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Enforce the unlimited-or-positive-limit rule against the merged state of
     * the request, so partial updates cannot leave the package in an invalid
     * "not unlimited and no limit" combination.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $package = $this->route('package');

            $isUnlimited = $this->has('is_unlimited')
                ? $this->boolean('is_unlimited')
                : (bool) ($package->is_unlimited ?? false);

            $dataLimit = $this->has('data_limit_gb')
                ? $this->input('data_limit_gb')
                : ($package->data_limit_gb ?? null);

            if (! $isUnlimited && ($dataLimit === null || (int) $dataLimit < 1)) {
                $validator->errors()->add(
                    'data_limit_gb',
                    'A data limit greater than 0 is required unless the package is unlimited.',
                );
            }
        });
    }
}
