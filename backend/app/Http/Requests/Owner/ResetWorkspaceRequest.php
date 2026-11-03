<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ResetWorkspaceRequest extends FormRequest
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
            'confirmation' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * The typed confirmation must equal the workspace slug exactly.
     *
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $slug = $this->user()?->workspace?->slug;

            if ($slug === null || ! hash_equals($slug, (string) $this->input('confirmation'))) {
                $validator->errors()->add('confirmation', __('messages.workspace_reset_confirmation_mismatch'));
            }
        }];
    }
}
