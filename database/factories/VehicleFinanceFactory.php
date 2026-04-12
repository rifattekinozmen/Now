<?php

namespace Database\Factories;

use App\Enums\VehicleFinanceType;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\VehicleFinance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleFinance>
 */
class VehicleFinanceFactory extends Factory
{
    protected $model = VehicleFinance::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(VehicleFinanceType::cases());
        $transactionDate = $this->faker->dateTimeBetween('-6 months', 'now');

        return [
            'tenant_id' => Tenant::factory(),
            'vehicle_id' => Vehicle::factory(),
            'finance_type' => $type->value,
            'amount' => $this->faker->randomFloat(2, 500, 50000),
            'currency_code' => 'TRY',
            'transaction_date' => $transactionDate->format('Y-m-d'),
            'due_date' => $this->faker->optional()->dateTimeBetween($transactionDate, '+2 months')?->format('Y-m-d'),
            'paid_at' => $this->faker->optional(0.7)->dateTimeBetween($transactionDate, 'now')?->format('Y-m-d'),
            'reference_no' => $this->faker->optional()->bothify('REF-######'),
            'description' => $this->faker->optional()->sentence(),
        ];
    }

    public function unpaid(): static
    {
        return $this->state(fn (array $attr) => [
            'paid_at' => null,
            'due_date' => now()->addDays(rand(1, 30))->format('Y-m-d'),
        ]);
    }
}
