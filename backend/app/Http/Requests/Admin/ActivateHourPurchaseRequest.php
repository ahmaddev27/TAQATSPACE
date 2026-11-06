<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Records the payment made outside the platform when activating a purchase.
 */
class ActivateHourPurchaseRequest extends FormRequest
{
    private const RECEIPT_MAX_KILOBYTES = 5120;

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
            'payment_method' => ['required', Rule::in(PaymentMethod::manualValues())],
            'paid_at' => ['nullable', 'date', 'before_or_equal:tomorrow'],
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::RECEIPT_MAX_KILOBYTES],
        ];
    }
}
