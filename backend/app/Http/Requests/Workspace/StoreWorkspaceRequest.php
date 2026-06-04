<?php

declare(strict_types=1);

namespace App\Http\Requests\Workspace;

use App\Enums\SeatType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Role enforcement is handled by the route middleware (role.owner).
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'total_seats' => ['required', 'integer', 'min:1', 'max:1000'],
            'price_per_month' => ['required', 'numeric', 'min:0'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', 'max:60'],
            'working_hours' => ['nullable', 'array'],

            // Optional per-seat-type pricing submitted alongside the workspace.
            'seat_types' => ['nullable', 'array'],
            'seat_types.*.type' => ['required', Rule::in(SeatType::values())],
            'seat_types.*.price_monthly' => ['nullable', 'numeric', 'min:0'],
            'seat_types.*.price_daily' => ['nullable', 'numeric', 'min:0'],
            'seat_types.*.capacity' => ['nullable', 'integer', 'min:0'],
            'seat_types.*.enabled' => ['boolean'],
        ];
    }
}
