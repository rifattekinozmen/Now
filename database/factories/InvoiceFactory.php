<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 100, 10000);
        $taxAmount = round($subtotal * 0.20, 2);

        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'order_id' => null,
            'invoice_no' => 'INV-'.$this->faker->numerify('####'),
            'invoice_date' => $this->faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'due_date' => $this->faker->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $subtotal + $taxAmount,
            'currency_code' => 'TRY',
            'status' => InvoiceStatus::Draft->value,
            'notes' => null,
            'meta' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state([
            'status' => InvoiceStatus::Sent->value,
        ]);
    }

    public function paid(): static
    {
        return $this->state([
            'status' => InvoiceStatus::Paid->value,
        ]);
    }

    public function overdue(): static
    {
        return $this->state([
            'status' => InvoiceStatus::Overdue->value,
            'due_date' => now()->subDays(10)->format('Y-m-d'),
        ]);
    }
}
