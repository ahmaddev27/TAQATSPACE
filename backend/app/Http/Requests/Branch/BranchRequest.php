<?php

declare(strict_types=1);

namespace App\Http\Requests\Branch;

use Illuminate\Foundation\Http\FormRequest;

/** Validates create/update of a branch; on update every field is optional. */
class BranchRequest extends FormRequest
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
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$presence, 'string', 'max:150'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
