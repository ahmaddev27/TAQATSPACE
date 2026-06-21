<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Dynamic cities: a real `cities` table + a denormalized FK on workspaces.
 *
 * Workspaces keep their existing free-string `city` column as a denormalized
 * display value (auto-filled from the chosen city's Arabic name on save) so the
 * many existing readers of `workspace.city` (analytics, explore filter, PDF,
 * exports, resources) keep working untouched. The new `city_id` FK is the
 * source of truth; admin renames propagate to the denormalized string.
 *
 * The backfill lives here (not in a seeder) so it runs exactly once, in-line
 * with the schema change, and is naturally idempotent via the one-shot
 * migration guarantee — no risk of a re-run double-creating cities.
 */
return new class extends Migration
{
    /**
     * The five cities the platform shipped with. Keyed by the legacy `city`
     * string keys that existing workspace rows may store.
     *
     * @var array<string, array{name_ar: string, name_en: string}>
     */
    private array $seedCities = [
        'gaza' => ['name_ar' => 'غزة', 'name_en' => 'Gaza'],
        'khanyounis' => ['name_ar' => 'خان يونس', 'name_en' => 'Khan Younis'],
        'rafah' => ['name_ar' => 'رفح', 'name_en' => 'Rafah'],
        'jabalia' => ['name_ar' => 'جباليا', 'name_en' => 'Jabalia'],
        'deirelbalah' => ['name_ar' => 'دير البلح', 'name_en' => 'Deir al-Balah'],
    ];

    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name_ar');
            $table->string('name_en');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('workspaces', function (Blueprint $table): void {
            $table->foreignUuid('city_id')->nullable()->after('city')
                ->constrained('cities')->nullOnDelete();
        });

        $this->seedAndBackfill();
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('city_id');
        });

        Schema::dropIfExists('cities');
    }

    /**
     * Seed the five known cities, then map every existing workspace onto a city:
     * match a known key, or a city's name_ar/name_en; otherwise create a city
     * from the raw string. Finally set `city_id` and normalise `city` to the
     * city's Arabic name so the denormalized column stays canonical.
     */
    private function seedAndBackfill(): void
    {
        // 1. Seed the known cities by their canonical Arabic name (idempotent).
        foreach ($this->seedCities as $city) {
            $this->ensureCity($city['name_ar'], $city['name_en']);
        }

        // 2. Backfill each distinct existing workspace city string.
        $distinct = DB::table('workspaces')
            ->whereNotNull('city')
            ->whereNull('city_id')
            ->distinct()
            ->pluck('city');

        foreach ($distinct as $raw) {
            $value = trim((string) $raw);

            if ($value === '') {
                continue;
            }

            $cityId = $this->resolveCityId($value);

            $arabicName = DB::table('cities')->where('id', $cityId)->value('name_ar');

            DB::table('workspaces')
                ->where('city', $raw)
                ->update(['city_id' => $cityId, 'city' => $arabicName]);
        }
    }

    /**
     * Resolve a raw workspace `city` string to a city id: a known legacy key,
     * else a match on name_ar/name_en, else a freshly created city.
     */
    private function resolveCityId(string $value): string
    {
        $key = Str::lower($value);

        if (isset($this->seedCities[$key])) {
            return $this->ensureCity(
                $this->seedCities[$key]['name_ar'],
                $this->seedCities[$key]['name_en'],
            );
        }

        $existing = DB::table('cities')
            ->where('name_ar', $value)
            ->orWhere('name_en', $value)
            ->value('id');

        if ($existing !== null) {
            return (string) $existing;
        }

        return $this->ensureCity($value, $value);
    }

    /**
     * Find-or-create a city by its Arabic name; returns the city id.
     */
    private function ensureCity(string $nameAr, string $nameEn): string
    {
        $existing = DB::table('cities')->where('name_ar', $nameAr)->value('id');

        if ($existing !== null) {
            return (string) $existing;
        }

        $id = (string) Str::uuid();

        DB::table('cities')->insert([
            'id' => $id,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
};
