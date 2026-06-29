<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    protected $model = City::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cities = [
            ['ar' => 'الرياض', 'en' => 'Riyadh'],
            ['ar' => 'جدة', 'en' => 'Jeddah'],
            ['ar' => 'الدمام', 'en' => 'Dammam'],
            ['ar' => 'مكة المكرمة', 'en' => 'Makkah'],
            ['ar' => 'المدينة المنورة', 'en' => 'Madinah'],
            ['ar' => 'الخبر', 'en' => 'Khobar'],
            ['ar' => 'أبها', 'en' => 'Abha'],
        ];

        $city = fake()->randomElement($cities);

        return [
            'name_ar' => $city['ar'],
            'name_en' => $city['en'],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
