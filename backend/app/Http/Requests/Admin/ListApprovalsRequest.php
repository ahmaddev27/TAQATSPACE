<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for the approvals lists. Authorization is enforced by the route
 * middleware (owner role / `approve_requests` permission).
 */
class ListApprovalsRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(ApprovalStatus::values())],
            'type' => ['nullable', Rule::in(ApprovalType::values())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
