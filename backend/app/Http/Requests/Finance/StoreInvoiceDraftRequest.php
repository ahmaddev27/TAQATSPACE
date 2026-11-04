<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A v2 invoice draft with its lines. A line tied to an unbilled charge takes
 * its description, quantity and price from that charge, so those are optional then.
 */
class StoreInvoiceDraftRequest extends FormRequest
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
            'customer_id' => ['required', 'uuid'],
            'due_date' => ['required', 'date'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'tax_rate' => ['nullable', 'decimal:0,2', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.unbilled_charge_id' => ['nullable', 'uuid'],
            'lines.*.catalog_item_id' => [
                'nullable',
                'uuid',
                Rule::exists('catalog_items', 'id')->where('workspace_id', $workspaceId),
            ],
            'lines.*.description' => ['required_without:lines.*.unbilled_charge_id', 'nullable', 'string', 'max:255'],
            'lines.*.qty' => ['required_without:lines.*.unbilled_charge_id', 'nullable', 'decimal:0,2', 'gt:0', 'max:9999999.99'],
            'lines.*.unit_price' => ['required_without:lines.*.unbilled_charge_id', 'nullable', 'decimal:0,2', 'min:0', 'max:9999999.99'],
        ];
    }
}
