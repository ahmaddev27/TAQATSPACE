<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ResourceStatus;
use App\Enums\ResourceType;
use App\Models\Resource;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<resource>
 */
class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->randomElement([
                'غرفة الاجتماعات الكبرى',
                'مكتب خاص A',
                'بروجكتر',
                'موقف سيارات',
                'قاعة التدريب',
            ]),
            'type' => fake()->randomElement(ResourceType::cases()),
            'quantity' => fake()->numberBetween(1, 10),
            'status' => fake()->randomElement(ResourceStatus::cases()),
            'notes' => fake()->optional(0.4)->sentence(),
        ];
    }

    public function available(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ResourceStatus::Available,
        ]);
    }
}
