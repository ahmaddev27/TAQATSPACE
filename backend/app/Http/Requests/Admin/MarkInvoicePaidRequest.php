<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the optional payment timestamp when an admin marks an invoice paid.
 * Authorization is enforced by the route middleware (auth:sanctum + role.admin).
 */
class MarkInvoicePaidRequest extends FormRequest
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
            'paid_at' => ['nullable', 'date', 'before_or_equal:tomorrow'],
        ];
    }
}
