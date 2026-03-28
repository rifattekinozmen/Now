<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plate' => strtoupper(fake()->unique()->bothify('##???##')),
            'vin' => fake()->optional()->bothify('?????????????????'),
            'brand' => fake()->optional()->randomElement(['Mercedes', 'Volvo', 'Scania', 'Ford']),
            'model' => fake()->optional()->word(),
            'manufacture_year' => fake()->optional()->numberBetween(2010, (int) date('Y')),
            'inspection_valid_until' => fake()->optional()->dateTimeBetween('now', '+1 year'),
            'meta' => null,
        ];
    }
}
