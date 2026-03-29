<?php

namespace Database\Factories;

use App\Models\FuelIntake;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelIntake>
 */
class FuelIntakeFactory extends Factory
{
    protected $model = FuelIntake::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'liters' => $this->faker->randomFloat(3, 20, 400),
            'odometer_km' => $this->faker->randomFloat(2, 10_000, 900_000),
            'recorded_at' => now(),
            'meta' => null,
        ];
    }
}
