<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\Gender;
use App\Enums\SeatType;
use App\Enums\UserRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Role-selection + completion for a freshly-provisioned SSO account.
 *
 * Account identity (name/email/password) already exists from the SSO provision,
 * so only the role choice and its role-specific profile are accepted here.
 */
class CompleteOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only a user who still needs onboarding may complete it. The controller
        // enforces auth; here we reject anyone already classified.
        return $this->user() !== null && $this->user()->needsOnboarding();
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('messages.onboarding_already_completed'));
    }

    /**
     * Role-conditional rules. Admins are never selectable.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'role' => ['required', Rule::in(UserRole::registerable())],
            'phone' => ['nullable', 'string', 'max:30'],
            // Optional for every role; null means "unspecified".
            'gender' => ['nullable', Rule::in(Gender::values())],
        ];

        if ($this->input('role') === UserRole::Freelancer->value) {
            $rules['specialty'] = ['required', 'string', 'max:120'];
            $rules['bio'] = ['nullable', 'string', 'max:1000'];
        }

        if ($this->input('role') === UserRole::WorkspaceOwner->value) {
            // The space's own name is submitted as `workspace_name`; the owner's
            // personal name already lives on the SSO-provisioned user record.
            $rules['workspace_name'] = ['required', 'string', 'min:2', 'max:150'];
            $rules['description'] = ['nullable', 'string', 'max:5000'];
            $rules['city_id'] = ['required', 'string', 'exists:cities,id'];
            $rules['area'] = ['nullable', 'string', 'max:120'];
            $rules['address'] = ['required', 'string', 'max:255'];
            $rules['lat'] = ['nullable', 'numeric', 'between:-90,90'];
            $rules['lng'] = ['nullable', 'numeric', 'between:-180,180'];
            $rules['capacity'] = ['nullable', 'integer', 'min:1', 'max:1000'];
            $rules['hours'] = ['nullable', 'string', 'max:120'];
            $rules['amenities'] = ['nullable', 'array'];
            $rules['amenities.*'] = ['string', 'max:60'];

            // Optional per-seat-type pricing the owner onboarding form may submit.
            $rules['seat_types'] = ['nullable', 'array'];
            $rules['seat_types.*.type'] = ['required', Rule::in(SeatType::values())];
            $rules['seat_types.*.price_monthly'] = ['nullable', 'numeric', 'min:0'];
            $rules['seat_types.*.price_daily'] = ['nullable', 'numeric', 'min:0'];
            $rules['seat_types.*.capacity'] = ['nullable', 'integer', 'min:0'];
            $rules['seat_types.*.enabled'] = ['boolean'];
        }

        return $rules;
    }
}
