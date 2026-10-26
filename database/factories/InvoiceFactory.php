<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state. Amounts are consistent: total = subtotal + 15% VAT.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(5, 200) * 100;
        $vat = round($subtotal * 0.15, 2);

        return [
            'client_id' => Client::factory(),
            'number' => 'INV-'.now()->year.'-'.fake()->unique()->numerify('#####'),
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->addDays(20)->toDateString(),
            'status' => InvoiceStatus::Draft,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'vat_rate' => '0.1500',
            'vat' => number_format($vat, 2, '.', ''),
            'total' => number_format($subtotal + $vat, 2, '.', ''),
            'currency' => 'SAR',
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Sent,
            'sent_at' => now()->subDays(9),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Overdue,
            'issue_date' => now()->subDays(45)->toDateString(),
            'due_date' => now()->subDays(15)->toDateString(),
            'sent_at' => now()->subDays(44),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Paid,
            'sent_at' => now()->subDays(9),
            'paid_at' => now()->subDays(2),
        ]);
    }
}
