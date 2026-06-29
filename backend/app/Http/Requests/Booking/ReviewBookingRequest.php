<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewBookingRequest extends FormRequest
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
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'seat_id' => ['nullable', 'string', 'uuid', 'exists:seats,id'],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
            // Approving a booking REQUIRES assigning an internet package that
            // belongs to the owner's workspace; the package is then provisioned
            // to the member. Rejecting needs no package.
            'package_id' => [
                Rule::requiredIf(fn (): bool => $this->input('action') === 'approve'),
                'string',
                'uuid',
                Rule::exists('internet_packages', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $this->workspaceId()),
                ),
            ],
        ];
    }

    /**
     * The reviewing owner's workspace id, used to scope the package ownership
     * rule so a package from another workspace fails validation.
     */
    private function workspaceId(): ?string
    {
        return $this->user()?->workspace?->id;
    }
}
