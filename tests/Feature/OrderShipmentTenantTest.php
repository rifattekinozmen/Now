<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Tenant;
use App\Models\User;

test('authenticated user only sees own tenant orders on admin orders page', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

    $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);
    Order::factory()->create([
        'customer_id' => $customerA->id,
        'order_number' => 'OR-TENANT-A-ONLY',
    ]);

    $this->actingAs($userB)
        ->get(route('admin.orders.index'))
        ->assertSuccessful()
        ->assertDontSee('OR-TENANT-A-ONLY');

    $this->actingAs($userA)
        ->get(route('admin.orders.index'))
        ->assertSuccessful()
        ->assertSee('OR-TENANT-A-ONLY');
});

test('order eloquent scope limits rows to current tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

    $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);
    $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);

    Order::factory()->create(['customer_id' => $customerA->id, 'order_number' => 'OX-A']);
    Order::factory()->create(['customer_id' => $customerB->id, 'order_number' => 'OX-B']);

    $this->actingAs($userB);

    expect(Order::query()->pluck('order_number')->all())->toBe(['OX-B']);
});

test('shipment eloquent scope limits rows to current tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

    $orderA = Order::factory()->create([
        'customer_id' => Customer::factory()->create(['tenant_id' => $tenantA->id]),
    ]);
    $orderB = Order::factory()->create([
        'customer_id' => Customer::factory()->create(['tenant_id' => $tenantB->id]),
    ]);

    Shipment::factory()->create(['order_id' => $orderA->id]);
    $shipmentB = Shipment::factory()->create(['order_id' => $orderB->id]);

    $this->actingAs($userB);

    expect(Shipment::query()->pluck('id')->all())->toBe([$shipmentB->id]);
});
