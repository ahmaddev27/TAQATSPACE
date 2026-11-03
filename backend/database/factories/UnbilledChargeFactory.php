<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UnbilledChargeSource;
use App\Enums\UnbilledChargeStatus;
use App\Models\UnbilledCharge;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnbilledCharge>
 */
class UnbilledChargeFactory extends Factory
{
    protected $model = UnbilledCharge::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->randomFloat(2, 1, 5);
        $unitPrice = fake()->randomFloat(2, 1, 100);

        return [
            'workspace_id' => Workspace::factory(),
            'customer_id' => User::factory(),
            'description' => fake()->words(3, true),
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'amount' => round($qty * $unitPrice, 2),
            'source' => UnbilledChargeSource::Manual->value,
            'status' => UnbilledChargeStatus::Open->value,
            'occurred_at' => now(),
        ];
    }

    public function status(UnbilledChargeStatus $status): static
    {
        return $this->state(['status' => $status->value]);
    }
}
