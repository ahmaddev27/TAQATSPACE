<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dueDate = fake()->dateTimeBetween('-3 months', '+1 month');

        return [
            'subscription_id' => Subscription::factory(),
            'amount' => fake()->randomElement([25, 30, 35, 40, 50]),
            'due_date' => $dueDate,
            'paid_at' => null,
            'status' => InvoiceStatus::Pending,
            'invoice_number' => 'TAQAT-'.date('Y').'-'.fake()->unique()->numerify('####'),
            'invoice_pdf_path' => null,
            'notes' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceStatus::Paid,
            'paid_at' => fake()->dateTimeBetween('-2 months', 'now'),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceStatus::Overdue,
            'due_date' => fake()->dateTimeBetween('-2 months', '-1 week'),
            'paid_at' => null,
        ]);
    }
}
