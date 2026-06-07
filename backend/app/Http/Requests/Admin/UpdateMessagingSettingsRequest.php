<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\Sms\SmsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the platform messaging payload `{ smtp?: {...}, sms?: {...} }`.
 * Every field is optional so partial updates work; secrets may be omitted or
 * sent blank to preserve the stored value. Authorization is handled by route
 * middleware (auth:sanctum + role.admin).
 */
class UpdateMessagingSettingsRequest extends FormRequest
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
            'smtp' => ['sometimes', 'array'],
            'smtp.host' => ['nullable', 'string', 'max:255'],
            'smtp.port' => ['nullable', 'integer', 'between:1,65535'],
            'smtp.username' => ['nullable', 'string', 'max:255'],
            'smtp.password' => ['nullable', 'string', 'max:1000'],
            'smtp.encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'smtp.from_address' => ['nullable', 'email', 'max:255'],
            'smtp.from_name' => ['nullable', 'string', 'max:255'],

            'sms' => ['sometimes', 'array'],
            'sms.provider' => ['nullable', Rule::in(SmsService::providers())],
            'sms.sender' => ['nullable', 'string', 'max:50'],
            'sms.username' => ['nullable', 'string', 'max:255'],
            'sms.password' => ['nullable', 'string', 'max:1000'],
            'sms.api_key' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The validated payload restricted to the smtp/sms blocks.
     *
     * @return array{smtp?: array<string, mixed>, sms?: array<string, mixed>}
     */
    public function messagingData(): array
    {
        /** @var array{smtp?: array<string, mixed>, sms?: array<string, mixed>} $data */
        $data = $this->only(['smtp', 'sms']);

        return $data;
    }
}
