<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SeatStatus;
use App\Enums\SeatType;
use App\Models\Seat;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seat>
 */
class SeatFactory extends Factory
{
    protected $model = Seat::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'seat_number' => strtoupper(fake()->bothify('?#')),
            'type' => fake()->randomElement(SeatType::cases()),
            'status' => SeatStatus::Available,
            'assigned_member_id' => null,
            'notes' => null,
        ];
    }
}
