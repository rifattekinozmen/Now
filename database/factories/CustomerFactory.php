<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'partner_number' => fake()->optional()->numerify('BP######'),
            'tax_id' => fake()->optional()->numerify('##########'),
            'legal_name' => fake()->company(),
            'trade_name' => fake()->optional()->company(),
            'payment_term_days' => fake()->randomElement([0, 30, 45, 60, 90]),
            'meta' => null,
        ];
    }
}
