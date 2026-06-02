<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\SeatType;
use App\Models\BookingRequest;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingRequest>
 */
class BookingRequestFactory extends Factory
{
    protected $model = BookingRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => User::factory()->freelancer(),
            'workspace_id' => Workspace::factory(),
            'preferred_seat_type' => fake()->randomElement(SeatType::cases())->value,
            'message' => 'أرغب بالانضمام إلى المساحة والحصول على مقعد ثابت.',
            'status' => BookingStatus::Pending,
            'reviewed_by' => null,
            'rejection_reason' => null,
            'reviewed_at' => null,
        ];
    }

    public function approved(User|string|null $reviewer = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BookingStatus::Approved,
            'reviewed_by' => $reviewer instanceof User ? $reviewer->id : $reviewer,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(User|string|null $reviewer = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BookingStatus::Rejected,
            'reviewed_by' => $reviewer instanceof User ? $reviewer->id : $reviewer,
            'rejection_reason' => 'لا تتوفر مقاعد شاغرة حالياً.',
            'reviewed_at' => now(),
        ]);
    }
}
