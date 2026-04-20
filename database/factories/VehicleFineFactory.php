<?php

namespace Database\Factories;

use App\Enums\VehicleFineStatus;
use App\Enums\VehicleFineType;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\VehicleFine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleFine>
 */
class VehicleFineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'vehicle_id' => Vehicle::factory(),
            'fine_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'amount' => $this->faker->randomFloat(2, 50, 5000),
            'currency_code' => 'TRY',
            'fine_type' => $this->faker->randomElement(VehicleFineType::cases())->value,
            'fine_no' => $this->faker->numerify('CEZA-####'),
            'location' => $this->faker->city(),
            'status' => VehicleFineStatus::Pending->value,
            'notes' => null,
            'meta' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state([
            'status' => VehicleFineStatus::Paid->value,
            'paid_at' => now(),
        ]);
    }

    public function appealed(): static
    {
        return $this->state([
            'status' => VehicleFineStatus::Appealed->value,
        ]);
    }
}
