<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

/**
 * The five cities the platform ships with. Idempotent (find-or-create by Arabic
 * name) so a fresh seed populates them while re-running on top of the migration's
 * own backfill is a no-op.
 */
class CitySeeder extends Seeder
{
    /** @var array<int, array{name_ar: string, name_en: string}> */
    private array $cities = [
        ['name_ar' => 'غزة', 'name_en' => 'Gaza'],
        ['name_ar' => 'خان يونس', 'name_en' => 'Khan Younis'],
        ['name_ar' => 'رفح', 'name_en' => 'Rafah'],
        ['name_ar' => 'جباليا', 'name_en' => 'Jabalia'],
        ['name_ar' => 'دير البلح', 'name_en' => 'Deir al-Balah'],
    ];

    public function run(): void
    {
        foreach ($this->cities as $city) {
            City::firstOrCreate(
                ['name_ar' => $city['name_ar']],
                ['name_en' => $city['name_en'], 'is_active' => true],
            );
        }
    }
}
