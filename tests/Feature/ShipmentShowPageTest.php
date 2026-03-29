<?php

use App\Enums\ShipmentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

test('user can view shipment detail page', function () {
    /** @var TestCase $this */
    /** @var User $user */
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

    $this->actingAs($user)
        ->get(route('admin.shipments.show', $shipment))
        ->assertSuccessful()
        ->assertSee((string) $shipment->id, false)
        ->assertSee(__('Lifecycle timeline'), false);
});

test('user cannot view shipment from another tenant', function () {
    /** @var TestCase $this */
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create(['tenant_id' => $tenantA->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenantB->id]);
    $order = Order::factory()->create([
        'tenant_id' => $tenantB->id,
        'customer_id' => $customer->id,
    ]);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
    ]);

    $this->actingAs($user)
        ->get(route('admin.shipments.show', $shipment))
        ->assertNotFound();
});

test('user can dispatch shipment from detail page', function () {
    /** @var TestCase $this */
    /** @var User $user */
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

    Livewire::test('pages::admin.shipment-show', ['shipment' => $shipment])
        ->call('markDispatched')
        ->assertHasNoErrors();

    expect($shipment->refresh()->status)->toBe(ShipmentStatus::Dispatched);
});
