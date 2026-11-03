<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PricingMethod;
use App\Models\Plan;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'code' => 'PLN-'.strtoupper(fake()->unique()->bothify('??####')),
            'name_ar' => 'خطة شهرية',
            'name_en' => 'Monthly plan',
            'pricing_method' => PricingMethod::Monthly->value,
            'price' => fake()->randomFloat(2, 50, 500),
        ];
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }

    public function metered(PricingMethod $method = PricingMethod::Hourly): static
    {
        return $this->state([
            'pricing_method' => $method->value,
            'included_quantity' => 10,
            'overage_price' => 2,
        ]);
    }
}
