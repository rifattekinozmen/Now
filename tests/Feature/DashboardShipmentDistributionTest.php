<?php

use App\Enums\ShipmentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Tests\TestCase;

test('dashboard shows shipment status distribution when shipments exist', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'tenant_id' => $user->tenant_id,
    ]);
    Shipment::factory()->create([
        'order_id' => $order->id,
        'status' => ShipmentStatus::Planned,
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee(__('Planned'), false)
        ->assertSee('data-shipment-chart', false);
});
