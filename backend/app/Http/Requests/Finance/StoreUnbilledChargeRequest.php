<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Repositories\Eloquent\UnbilledChargeRepository;
use App\Services\Finance\CatalogAccess;
use App\Support\Tenancy\CurrentWorkspaceContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Manual unbilled charge. `source` and `amount` are never read from the client:
 * the source is always manual and the amount is computed by the service.
 */
class StoreUnbilledChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(CatalogAccess::class)->canManage($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $workspaceId = app(CurrentWorkspaceContext::class)->id();

        return [
            'customer_id' => ['required', 'uuid', $this->customerRule()],
            'subscription_id' => [
                'nullable',
                'uuid',
                Rule::exists('subscriptions', 'id')
                    ->where('workspace_id', $workspaceId)
                    ->where('member_id', $this->input('customer_id')),
            ],
            'catalog_item_id' => [
                'nullable',
                'uuid',
                Rule::exists('catalog_items', 'id')->where('workspace_id', $workspaceId),
            ],
            'description' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'unit_price' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'source_ref' => ['nullable', 'string', 'max:100'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

    private function customerRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $workspace = app(CurrentWorkspaceContext::class)->workspace();

            if (! app(UnbilledChargeRepository::class)->isCustomerOf($workspace, (string) $value)) {
                $fail(__('messages.unbilled_charge_unknown_customer'));
            }
        };
    }
}
