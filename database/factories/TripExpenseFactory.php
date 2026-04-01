<?php

namespace Database\Factories;

use App\Enums\ExpenseType;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TripExpense;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripExpense>
 */
class TripExpenseFactory extends Factory
{
    protected $model = TripExpense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'vehicle_id' => Vehicle::factory(),
            'employee_id' => null,
            'shipment_id' => null,
            'expense_type' => fake()->randomElement(ExpenseType::cases()),
            'amount' => fake()->randomFloat(2, 20, 5000),
            'currency_code' => 'TRY',
            'expense_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'odometer_km' => fake()->optional(0.6)->randomFloat(1, 50000, 500000),
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function forVehicle(int $vehicleId): static
    {
        return $this->state(['vehicle_id' => $vehicleId]);
    }

    public function withDriver(): static
    {
        return $this->state(['employee_id' => Employee::factory()]);
    }

    public function fuel(): static
    {
        return $this->state(['expense_type' => ExpenseType::Fuel]);
    }
}
