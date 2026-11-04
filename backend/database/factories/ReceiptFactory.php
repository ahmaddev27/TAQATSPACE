<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $at = now();

        return [
            'workspace_id' => Workspace::factory(),
            'customer_id' => User::factory()->freelancer(),
            'number' => 'REC-'.fake()->unique()->numerify('######'),
            'method' => PaymentMethod::Cash,
            'amount' => '100.00',
            'received_at' => $at,
            'collected_at' => $at,
        ];
    }
}
