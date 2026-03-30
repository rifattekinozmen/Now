<?php

namespace Database\Factories;

use App\Models\FuelPrice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelPrice>
 */
class FuelPriceFactory extends Factory
{
    protected $model = FuelPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'fuel_type' => $this->faker->randomElement(['diesel', 'gasoline', 'lpg']),
            'price' => $this->faker->randomFloat(4, 10, 100),
            'currency' => $this->faker->randomElement(['TRY', 'EUR']),
            'recorded_at' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'source' => $this->faker->optional()->company(),
            'region' => $this->faker->optional()->city(),
        ];
    }
}
