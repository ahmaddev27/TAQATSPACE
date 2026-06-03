<?php

declare(strict_types=1);

namespace App\Http\Requests\Package;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePackageRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'speed_mbps' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'data_limit_gb' => ['nullable', 'integer', 'min:1'],
            'is_unlimited' => ['required', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Enforce: a package must either be unlimited OR declare a positive data limit.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $isUnlimited = $this->boolean('is_unlimited');
            $dataLimit = $this->input('data_limit_gb');

            if (! $isUnlimited && ($dataLimit === null || (int) $dataLimit < 1)) {
                $validator->errors()->add(
                    'data_limit_gb',
                    'A data limit greater than 0 is required unless the package is unlimited.',
                );
            }
        });
    }
}
