<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

/** Validation of an `allocations` list: invoice and amount per row, one row per invoice. */
trait AllocationRules
{
    /**
     * @return array<string, mixed>
     */
    protected function allocationRules(): array
    {
        return [
            'allocations.*.invoice_id' => ['required', 'uuid', 'distinct'],
            'allocations.*.amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
        ];
    }
}
