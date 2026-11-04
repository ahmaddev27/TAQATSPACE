<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Enums\BillingMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Financial terms of a subscription. Every field is optional so the owner can
 * patch a subset; sending null clears the plan, custom price or hours.
 * Cross-field rules (plan vs hours vs seat) live in the service.
 */
class UpdateSubscriptionFinancialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->workspace !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $workspaceId = $this->user()?->workspace?->id;

        return [
            'plan_id' => [
                'sometimes', 'nullable', 'uuid',
                Rule::exists('plans', 'id')->where('workspace_id', $workspaceId),
            ],
            'billing_mode' => ['sometimes', 'required', Rule::enum(BillingMode::class)],
            'custom_price' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'contracted_hours' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'grace_days' => ['sometimes', 'required', 'integer', 'min:0', 'max:365'],
            'auto_renew' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
