<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\SeatType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Role-conditional rules. Admins are never publicly registerable.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(UserRole::registerable())],
            'phone' => ['nullable', 'string', 'max:30'],
        ];

        if ($this->input('role') === UserRole::Freelancer->value) {
            $rules['specialty'] = ['nullable', 'string', 'max:120'];
            $rules['bio'] = ['nullable', 'string', 'max:1000'];
        }

        if ($this->input('role') === UserRole::WorkspaceOwner->value) {
            // Workspace profile — `name` above is the OWNER's personal name; the
            // space's own name is submitted as `workspace_name`.
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

            $rules['license_file'] = ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'];
            $rules['id_document'] = ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'];

            // Optional per-seat-type pricing the owner registration form may submit.
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
