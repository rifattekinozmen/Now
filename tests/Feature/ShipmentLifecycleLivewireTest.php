<?php

use App\Enums\ShipmentStatus;
use Tests\TestCase;

uses(TestCase::class);
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Livewire\Livewire;

test('user can dispatch and deliver shipment via livewire', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create([
        'tenant_id' => $user->tenant_id,
        'customer_id' => $customer->id,
    ]);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'status' => ShipmentStatus::Planned,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::admin.shipments-index')
        ->call('markDispatched', $shipment->id)
        ->assertHasNoErrors();

    expect($shipment->refresh()->status)->toBe(ShipmentStatus::Dispatched);

    Livewire::test('pages::admin.shipments-index')
        ->call('markDelivered', $shipment->id)
        ->assertHasNoErrors();

    expect($shipment->refresh()->status)->toBe(ShipmentStatus::Delivered);
});
