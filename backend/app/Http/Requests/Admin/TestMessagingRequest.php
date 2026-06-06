<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the messaging test payload `{ channel: "email"|"sms", to }`.
 * For email, `to` must be a valid address; for SMS it is a free-form recipient
 * number. Authorization is handled by route middleware (auth:sanctum +
 * role.admin).
 */
class TestMessagingRequest extends FormRequest
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
            'channel' => ['required', Rule::in(['email', 'sms'])],
            'to' => array_merge(
                ['required', 'string', 'max:255'],
                $this->input('channel') === 'email' ? ['email'] : [],
            ),
        ];
    }
}
