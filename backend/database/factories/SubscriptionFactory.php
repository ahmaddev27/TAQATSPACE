<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PlanType;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => User::factory()->freelancer(),
            'workspace_id' => Workspace::factory(),
            'seat_id' => null,
            'plan_type' => PlanType::Monthly,
            'start_date' => fake()->dateTimeBetween('-6 months', '-1 month'),
            'end_date' => null,
            'monthly_price' => fake()->randomElement([25, 30, 35, 40, 50]),
            'status' => SubscriptionStatus::Active,
            'cancelled_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriptionStatus::Expired,
            'start_date' => fake()->dateTimeBetween('-14 months', '-8 months'),
            'end_date' => fake()->dateTimeBetween('-7 months', '-2 months'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => fake()->dateTimeBetween('-6 months', '-1 month'),
        ]);
    }
}
