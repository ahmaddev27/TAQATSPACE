<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CatalogKind;
use App\Models\CatalogCategory;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogCategory>
 */
class CatalogCategoryFactory extends Factory
{
    protected $model = CatalogCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'kind' => CatalogKind::Sales->value,
            'code' => 'CAT-'.strtoupper(fake()->unique()->bothify('??##??')),
            'name_ar' => fake()->randomElement(['مشروبات', 'خدمات', 'قرطاسية']),
            'name_en' => fake()->randomElement(['Drinks', 'Services', 'Stationery']),
            'sort_order' => 0,
        ];
    }

    public function expense(): static
    {
        return $this->state(['kind' => CatalogKind::Expense->value]);
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }
}
