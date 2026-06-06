<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Services\Sms\SmsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a workspace owner's own messaging payload
 * `{ use_platform?: bool, smtp?: {...}, sms?: {...} }`. Same secret-blank rules
 * as the platform request: omit or blank a secret to keep the stored value.
 * Ownership is enforced via the 'manage-workspace' gate in the controller.
 */
class UpdateWorkspaceMessagingRequest extends FormRequest
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
            'use_platform' => ['sometimes', 'boolean'],

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
     * The validated payload restricted to the messaging blocks.
     *
     * @return array{use_platform?: bool, smtp?: array<string, mixed>, sms?: array<string, mixed>}
     */
    public function messagingData(): array
    {
        /** @var array{use_platform?: bool, smtp?: array<string, mixed>, sms?: array<string, mixed>} $data */
        $data = $this->only(['use_platform', 'smtp', 'sms']);

        return $data;
    }
}
