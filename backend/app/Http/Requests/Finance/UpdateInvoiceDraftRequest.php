<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

/** Replaces a draft's lines and header; the customer cannot change. */
class UpdateInvoiceDraftRequest extends StoreInvoiceDraftRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['customer_id']);
        $rules['due_date'] = ['sometimes', 'date'];

        return $rules;
    }
}
