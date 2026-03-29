<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'order_number' => 'ORD-'.strtoupper($this->faker->unique()->bothify('########')),
            'sas_no' => $this->faker->optional()->bothify('SAS-######'),
            'status' => OrderStatus::Draft,
            'ordered_at' => now(),
            'currency_code' => $this->faker->randomElement(['TRY', 'EUR', 'USD']),
            'freight_amount' => $this->faker->optional()->randomFloat(2, 100, 50_000),
            'exchange_rate' => null,
            'distance_km' => $this->faker->optional()->randomFloat(2, 10, 2000),
            'tonnage' => $this->faker->optional()->randomFloat(3, 1, 40),
            'gross_weight_kg' => null,
            'tara_weight_kg' => null,
            'net_weight_kg' => null,
            'moisture_percent' => null,
            'incoterms' => $this->faker->optional()->randomElement(['EXW', 'FOB', 'CIF', 'DDP']),
            'loading_site' => $this->faker->optional()->city(),
            'unloading_site' => $this->faker->optional()->city(),
            'meta' => null,
        ];
    }
}
