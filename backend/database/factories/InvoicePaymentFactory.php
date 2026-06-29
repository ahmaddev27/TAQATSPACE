<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoicePayment>
 */
class InvoicePaymentFactory extends Factory
{
    protected $model = InvoicePayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount' => fake()->randomElement([25, 30, 35, 40, 50]),
            'receipt_path' => null,
            'paid_at' => fake()->dateTimeBetween('-2 months', 'now'),
        ];
    }

    public function withReceipt(): static
    {
        return $this->state(fn (array $attributes): array => [
            'receipt_path' => 'receipts/'.fake()->uuid().'.pdf',
        ]);
    }
}
