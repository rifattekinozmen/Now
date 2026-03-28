<?php

namespace Database\Factories;

use App\Enums\DeliveryNumberStatus;
use App\Models\DeliveryNumber;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryNumber>
 */
class DeliveryNumberFactory extends Factory
{
    protected $model = DeliveryNumber::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'pin_code' => fake()->unique()->numerify('#########'),
            'sas_no' => fake()->optional()->bothify('SAS-####'),
            'status' => DeliveryNumberStatus::Available,
            'order_id' => null,
            'shipment_id' => null,
            'assigned_at' => null,
            'used_at' => null,
            'meta' => null,
        ];
    }
}
