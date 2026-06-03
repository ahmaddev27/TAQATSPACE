<?php

declare(strict_types=1);

namespace App\Http\Requests\Package;

use Illuminate\Foundation\Http\FormRequest;

class AssignPackageRequest extends FormRequest
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
            'member_id' => ['required', 'string', 'exists:users,id'],
        ];
    }
}
