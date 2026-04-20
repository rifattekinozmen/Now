<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'label' => $this->faker->randomElement(['Ana Depo', 'Fabrika', 'Şantiye', 'Müşteri Deposu', 'Liman']),
            'address_line' => $this->faker->streetAddress(),
            'city' => $this->faker->randomElement(['İstanbul', 'Ankara', 'İzmir', 'Bursa', 'Adana']),
            'district' => $this->faker->city(),
            'postal_code' => $this->faker->numerify('#####'),
            'country_code' => 'TR',
            'contact_name' => $this->faker->name(),
            'contact_phone' => $this->faker->phoneNumber(),
            'is_default' => false,
            'notes' => null,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
