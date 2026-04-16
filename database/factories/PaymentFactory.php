<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'amount' => fake()->randomFloat(2, 100, 100000),
            'currency_code' => 'TRY',
            'payment_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'due_date' => fake()->optional()->dateTimeBetween('now', '+90 days')?->format('Y-m-d'),
            'payment_method' => fake()->randomElement(PaymentMethod::cases())->value,
            'status' => PaymentStatus::Pending->value,
            'reference_no' => fake()->optional()->numerify('PAY-####'),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => PaymentStatus::Pending->value]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => PaymentStatus::Completed->value,
            'approved_at' => now(),
        ]);
    }

    public function bankTransfer(): static
    {
        return $this->state(['payment_method' => PaymentMethod::BankTransfer->value]);
    }
}
