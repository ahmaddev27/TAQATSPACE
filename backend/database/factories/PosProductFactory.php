<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PosProduct;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosProduct>
 */
class PosProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->randomElement(['قهوة', 'شاي', 'ماء', 'سندويش', 'كيك']),
            'category' => fake()->randomElement(['مشروبات', 'مأكولات', null]),
            'sku' => null,
            'price' => fake()->randomFloat(2, 1, 20),
            'track_stock' => true,
            'stock_qty' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }

    public function untracked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'track_stock' => false,
            'stock_qty' => 0,
        ]);
    }
}
