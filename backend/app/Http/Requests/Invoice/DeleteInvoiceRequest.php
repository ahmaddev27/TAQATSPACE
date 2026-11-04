<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use App\Enums\WorkspaceFeature;
use App\Http\Requests\Finance\VoidInvoiceRequest;
use App\Models\Invoice;
use App\Services\FeatureFlagService;

/**
 * DELETE /workspace/invoices/{invoice}: a reason is needed only when the delete
 * turns into a void (an issued invoice in a finance-v2 workspace).
 */
class DeleteInvoiceRequest extends VoidInvoiceRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $invoice = $this->route('invoice');
        $becomesVoid = $invoice instanceof Invoice
            && ! $invoice->isDraft()
            && $invoice->workspace_id !== null
            && app(FeatureFlagService::class)->isEnabled($invoice->workspace_id, WorkspaceFeature::FinanceV2);

        return $becomesVoid ? parent::rules() : ['reason' => ['nullable', 'string', 'max:500']];
    }
}
