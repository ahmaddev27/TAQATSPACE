<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Enums\EducationLevel;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\Specialization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberProfileRequest extends FormRequest
{
    /** Owner-only access is enforced by the route's role middleware. */
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
            'gender' => ['sometimes', 'nullable', Rule::enum(Gender::class)],
            'education_level' => ['sometimes', 'nullable', Rule::enum(EducationLevel::class)],
            'specialization' => ['sometimes', 'nullable', Rule::enum(Specialization::class)],
            'employment_status' => ['sometimes', 'nullable', Rule::enum(EmploymentStatus::class)],
            'mikrotik_username' => ['sometimes', 'string', 'min:3', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
        ];
    }
}
