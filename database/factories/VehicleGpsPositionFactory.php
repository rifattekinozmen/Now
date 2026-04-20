<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\VehicleGpsPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleGpsPosition>
 */
class VehicleGpsPositionFactory extends Factory
{
    protected $model = VehicleGpsPosition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Turkey bounding box approx: lat 36–42, lng 26–45
        return [
            'tenant_id' => Tenant::factory(),
            'vehicle_id' => Vehicle::factory(),
            'lat' => fake()->randomFloat(7, 36.0, 42.0),
            'lng' => fake()->randomFloat(7, 26.0, 45.0),
            'speed' => fake()->optional()->randomFloat(2, 0, 120),
            'heading' => fake()->optional()->randomFloat(2, 0, 360),
            'recorded_at' => now()->subMinutes(fake()->numberBetween(1, 60)),
        ];
    }
}
