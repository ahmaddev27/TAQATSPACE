<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WorkspaceStatus;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\GazaData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $location = GazaData::location();
        $coords = GazaData::coordinates();

        return [
            'owner_id' => User::factory()->owner(),
            'name' => fake()->randomElement(GazaData::WORKSPACE_NAMES),
            'description' => 'مساحة عمل مشتركة في '.$location['area'].' توفّر بيئة مهنية هادئة وإنترنت سريع.',
            'address' => $location['area'].'، '.$location['city'],
            'city' => $location['city'],
            'phone' => GazaData::phone(),
            'latitude' => $coords['lat'],
            'longitude' => $coords['lng'],
            'total_seats' => fake()->numberBetween(10, 20),
            'price_per_month' => fake()->randomElement([25, 30, 35, 40, 50]),
            'amenities' => GazaData::amenities(),
            'photos' => ['workspaces/seed/1.jpg', 'workspaces/seed/2.jpg'],
            'working_hours' => GazaData::workingHours(),
            'status' => WorkspaceStatus::Active,
            'avg_rating' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WorkspaceStatus::Pending,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WorkspaceStatus::Suspended,
        ]);
    }
}
