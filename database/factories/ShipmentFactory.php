<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'vehicle_id' => null,
            'status' => ShipmentStatus::Planned,
            'dispatched_at' => null,
            'delivered_at' => null,
            'meta' => null,
            'is_return' => false,
            'return_reason' => null,
            'return_photo_path' => null,
        ];
    }
}
