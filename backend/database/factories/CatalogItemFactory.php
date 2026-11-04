<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CatalogUnit;
use App\Models\CatalogCategory;
use App\Models\CatalogItem;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogItem>
 */
class CatalogItemFactory extends Factory
{
    protected $model = CatalogItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'catalog_category_id' => fn (array $attributes) => CatalogCategory::factory()->create([
                'workspace_id' => $attributes['workspace_id'],
            ])->id,
            'code' => 'ITM-'.strtoupper(fake()->unique()->bothify('??####')),
            'name_ar' => fake()->randomElement(['قهوة', 'طباعة', 'حجز قاعة']),
            'name_en' => fake()->randomElement(['Coffee', 'Printing', 'Room booking']),
            'unit' => CatalogUnit::Piece->value,
            'price' => fake()->randomFloat(2, 1, 100),
        ];
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }
}
