<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

/** Authorization is the controller's workspace-ownership check (behind role.owner). */
class PushRouterScriptUpdateRequest extends FormRequest
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
            'full' => ['sometimes', 'boolean'],
        ];
    }

    public function wantsFullUpdate(): bool
    {
        return $this->boolean('full');
    }
}
