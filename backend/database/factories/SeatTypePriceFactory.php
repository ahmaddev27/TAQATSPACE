<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SeatType;
use App\Models\SeatTypePrice;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeatTypePrice>
 */
class SeatTypePriceFactory extends Factory
{
    protected $model = SeatTypePrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'type' => fake()->randomElement(SeatType::cases()),
            'price_monthly' => fake()->randomFloat(2, 50, 500),
            'price_daily' => fake()->randomFloat(2, 5, 30),
            'capacity' => fake()->numberBetween(0, 30),
            'enabled' => true,
        ];
    }
}
