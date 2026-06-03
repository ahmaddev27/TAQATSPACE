<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class MarkPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Optional payment timestamp; defaults to now() in the service.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
