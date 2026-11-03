<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Enums\UnbilledChargeSource;
use App\Enums\UnbilledChargeStatus;
use App\Services\Finance\CatalogAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUnbilledChargesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(CatalogAccess::class)->canView($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'uuid'],
            'source' => ['nullable', Rule::enum(UnbilledChargeSource::class)],
            'status' => ['nullable', Rule::enum(UnbilledChargeStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
