<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InternetPackage;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternetPackage>
 */
class InternetPackageFactory extends Factory
{
    protected $model = InternetPackage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unlimited = fake()->boolean(40);

        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->randomElement(['أساسي', 'متقدم', 'احترافي']),
            'speed_mbps' => fake()->randomElement([20, 50, 100, 200]),
            'price' => fake()->randomElement([0, 5, 10, 15]),
            'data_limit_gb' => $unlimited ? null : fake()->randomElement([50, 100, 200]),
            'is_unlimited' => $unlimited,
            'is_active' => true,
        ];
    }
}
