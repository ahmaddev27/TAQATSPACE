<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PartnerClient;
use App\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_client_id' => PartnerClient::factory(),
            'event' => fake()->randomElement(['booking.created', 'booking.updated', 'subscription.created']),
            'payload' => ['id' => fake()->uuid(), 'status' => 'pending'],
            'status' => 'pending',
            'attempts' => 0,
            'response_code' => null,
            'last_attempted_at' => null,
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'delivered',
            'attempts' => 1,
            'response_code' => 200,
            'last_attempted_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'failed',
            'attempts' => fake()->numberBetween(1, 5),
            'response_code' => fake()->randomElement([400, 500, 503]),
            'last_attempted_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }
}
