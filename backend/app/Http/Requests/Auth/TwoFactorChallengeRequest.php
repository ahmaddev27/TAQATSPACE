<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/** Either an authenticator code or a recovery code (challenge, step-up, disable). */
class TwoFactorChallengeRequest extends FormRequest
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
            'code' => ['required_without:recovery_code', 'nullable', 'string', 'digits:6'],
            'recovery_code' => ['required_without:code', 'nullable', 'string', 'max:32'],
        ];
    }
}
