<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;

test('guest can view public shipment track page with valid token', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create(['customer_id' => $customer->id]);
    $shipment = Shipment::factory()->create(['order_id' => $order->id]);

    $this->get(route('track.shipment', ['token' => $shipment->public_reference_token]))
        ->assertSuccessful()
        ->assertSee($order->order_number, escape: false);
});

test('invalid tracking token returns 404', function () {
    $this->get(route('track.shipment', ['token' => 'invalid-token-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx']))
        ->assertNotFound();
});
