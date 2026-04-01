<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\PricingCondition;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingCondition>
 */
class PricingConditionFactory extends Factory
{
    protected $model = PricingCondition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cities = ['İstanbul', 'Ankara', 'İzmir', 'Adana', 'Bursa', 'Mersin', 'Konya', 'Kayseri'];

        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'name' => fake()->words(3, true).' Navlun Sözleşmesi',
            'contract_no' => fake()->optional()->bothify('CNT-####-??'),
            'material_code' => fake()->optional()->randomElement(['CEM-0101-DOK', 'CLN-0100', 'CEM-0550-T50', null]),
            'route_from' => fake()->randomElement($cities),
            'route_to' => fake()->randomElement($cities),
            'distance_km' => fake()->randomFloat(1, 50, 1200),
            'base_price' => fake()->randomFloat(2, 500, 5000),
            'currency_code' => 'TRY',
            'price_per_ton' => fake()->randomFloat(4, 50, 300),
            'min_tonnage' => fake()->randomElement([0, 20, 25, 26]),
            'valid_from' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'valid_until' => fake()->optional(0.7)->dateTimeBetween('now', '+2 years')?->format('Y-m-d'),
            'is_active' => true,
        ];
    }

    public function expiringSoon(): static
    {
        return $this->state([
            'valid_until' => now()->addDays(fake()->numberBetween(1, 29))->format('Y-m-d'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
