<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\SeatType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the onboarding seat-setup payload: a per-type seat count the
 * pending workspace owner chooses right after registering. Only a workspace
 * owner who already has a workspace may submit it.
 */
class OnboardingSeatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->role === UserRole::WorkspaceOwner
            && $user->workspace !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'seats' => ['required', 'array', 'min:1'],
            'seats.*.type' => ['required', Rule::in(SeatType::values())],
            'seats.*.count' => ['required', 'integer', 'min:0', 'max:200'],
        ];
    }
}
