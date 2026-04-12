<?php

namespace Database\Factories;

use App\Enums\TyrePosition;
use App\Enums\TyreStatus;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\VehicleTyre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleTyre>
 */
class VehicleTyreFactory extends Factory
{
    protected $model = VehicleTyre::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'vehicle_id' => Vehicle::factory(),
            'brand' => $this->faker->randomElement(['Bridgestone', 'Michelin', 'Goodyear', 'Pirelli', 'Continental', 'Lassa', 'Petlas']),
            'size' => $this->faker->randomElement(['315/80R22.5', '295/80R22.5', '385/65R22.5', '275/70R22.5']),
            'position' => $this->faker->randomElement(TyrePosition::cases())->value,
            'installed_at' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'km_installed' => $this->faker->numberBetween(50000, 300000),
            'removed_at' => null,
            'km_removed' => null,
            'status' => TyreStatus::Active->value,
            'tread_depth_mm' => $this->faker->randomFloat(1, 2.0, 12.0),
            'supplier' => $this->faker->optional()->company(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function worn(): static
    {
        return $this->state(fn (array $attr) => [
            'status' => TyreStatus::Worn->value,
            'tread_depth_mm' => $this->faker->randomFloat(1, 1.0, 3.0),
        ]);
    }

    public function removed(): static
    {
        return $this->state(fn (array $attr) => [
            'status' => TyreStatus::Removed->value,
            'removed_at' => now()->subDays(rand(1, 90))->format('Y-m-d'),
            'km_removed' => $this->faker->numberBetween(300000, 600000),
        ]);
    }
}
