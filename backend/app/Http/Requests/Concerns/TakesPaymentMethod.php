<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\PaymentMethod;
use App\Enums\WorkspaceFeature;
use App\Models\Invoice;
use App\Services\FeatureFlagService;
use Illuminate\Validation\Rule;

/**
 * The optional `method` of a mark-paid request. It only means something with
 * finance v2 on, where the payment becomes a receipt of that method. With the
 * flag off it is neither validated nor passed on, so the request behaves as it
 * always did and an unknown value is simply ignored.
 */
trait TakesPaymentMethod
{
    /**
     * @param  array<int, mixed>  $extra  further rules, used with finance v2 on only
     * @return array<string, mixed>
     */
    protected function paymentMethodRules(array $extra = []): array
    {
        if (! $this->invoiceUsesFinanceV2()) {
            return [];
        }

        // A cheque is recorded as a receipt and clears later; it is never "marked paid".
        $methods = [PaymentMethod::Cash->value, PaymentMethod::Card->value, PaymentMethod::Transfer->value];

        return ['method' => ['nullable', Rule::in($methods), ...$extra]];
    }

    /** The validated method, or null when none was given or finance v2 is off. */
    public function paymentMethod(): ?PaymentMethod
    {
        $method = $this->validated('method');

        return $method === null ? null : PaymentMethod::from((string) $method);
    }

    private function invoiceUsesFinanceV2(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice
            && $invoice->workspace_id !== null
            && $this->container->make(FeatureFlagService::class)->isEnabled($invoice->workspace_id, WorkspaceFeature::FinanceV2);
    }
}
