<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Enums\PricingMethod;
use App\Services\Finance\PlanAccess;
use App\Support\Tenancy\CurrentWorkspaceContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Base for plan writes. Writes carry the whole plan, so which fields are
 * required (and allowed) follows the chosen pricing method.
 */
abstract class PlanRequest extends FormRequest
{
    private const MONEY = ['numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(PlanAccess::class)->canManage($user);
    }

    protected function workspaceId(): ?string
    {
        return app(CurrentWorkspaceContext::class)->id();
    }

    /**
     * @return array<string, mixed>
     */
    protected function planRules(): array
    {
        $method = PricingMethod::tryFrom((string) $this->input('pricing_method'));

        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'pricing_method' => ['required', Rule::enum(PricingMethod::class)],
            'price' => ['required', ...self::MONEY],
            'overage_price' => [$method?->isMetered() ? 'required' : 'nullable', ...self::MONEY],
            'included_quantity' => [
                $this->requiresAllowance($method) ? 'required' : 'nullable',
                'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99',
            ],
            'validity_days' => [$method?->isBundle() ? 'required' : 'nullable', 'integer', 'min:1', 'max:3650'],
            'is_dedicated_seat' => ['sometimes', 'boolean'],
            'promo_price' => ['nullable', 'required_with:promo_until', ...self::MONEY, 'lt:price'],
            'promo_until' => ['nullable', 'required_with:promo_price', 'date'],
        ];
    }

    /**
     * BR-FIN-04, checked here so the client gets a field-level 422.
     */
    public function after(): array
    {
        return [function ($validator): void {
            $method = PricingMethod::tryFrom((string) $this->input('pricing_method'));

            if ($method !== null && $this->boolean('is_dedicated_seat') && ! $method->allowsDedicatedSeat()) {
                $validator->errors()->add('is_dedicated_seat', __('messages.plan_dedicated_seat_not_allowed'));
            }
        }];
    }

    private function requiresAllowance(?PricingMethod $method): bool
    {
        return $method !== null && ($method->isMetered() || $method->isBundle());
    }
}
