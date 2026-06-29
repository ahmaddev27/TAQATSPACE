<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PartnerClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PartnerClient>
 */
class PartnerClientFactory extends Factory
{
    protected $model = PartnerClient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'api_key_hash' => PartnerClient::hashKey(Str::random(40)),
            'webhook_url' => fake()->url(),
            'webhook_secret' => Str::random(32),
            'is_active' => true,
            'scopes' => ['bookings:read', 'bookings:write'],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
