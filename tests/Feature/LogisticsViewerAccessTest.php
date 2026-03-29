<?php

use App\Enums\ShipmentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

test('logistics viewer can open admin customer index', function () {
    /** @var TestCase $this */
    /** @var User $user */
    $user = User::factory()->logisticsViewer()->create();

    $this->actingAs($user)
        ->get(route('admin.customers.index'))
        ->assertSuccessful();
});

test('logistics viewer cannot create customer via livewire', function () {
    /** @var TestCase $this */
    /** @var User $user */
    $user = User::factory()->logisticsViewer()->create();

    $this->actingAs($user);

    Livewire::test('pages::admin.customers-index')
        ->set('legal_name', 'Viewer Blocked AS')
        ->set('tax_id', '')
        ->set('trade_name', '')
        ->call('saveCustomer')
        ->assertForbidden();
});

test('logistics viewer can open shipment detail page', function () {
    /** @var TestCase $this */
    /** @var User $user */
    $user = User::factory()->logisticsViewer()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
    ]);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'status' => ShipmentStatus::Planned,
    ]);

    $this->actingAs($user)
        ->get(route('admin.shipments.show', $shipment))
        ->assertSuccessful()
        ->assertSee(__('Lifecycle timeline'), false);
});

test('logistics viewer cannot dispatch from shipment detail page', function () {
    /** @var TestCase $this */
    /** @var User $user */
    $user = User::factory()->logisticsViewer()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
    ]);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'status' => ShipmentStatus::Planned,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::admin.shipment-show', ['shipment' => $shipment])
        ->call('markDispatched')
        ->assertForbidden();
});

test('logistics viewer cannot dispatch shipment via livewire', function () {
    /** @var TestCase $this */
    /** @var User $user */
    $user = User::factory()->logisticsViewer()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
    ]);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'status' => ShipmentStatus::Planned,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::admin.shipments-index')
        ->call('markDispatched', $shipment->id)
        ->assertForbidden();
});
