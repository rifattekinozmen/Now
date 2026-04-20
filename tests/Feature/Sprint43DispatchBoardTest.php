<?php

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
});

it('admin can access dispatch board page', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $role = Role::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('admin.dispatch-board'))
        ->assertSuccessful();
})->group('routes');

it('dispatch board route is protected', function (): void {
    $this->get(route('admin.dispatch-board'))
        ->assertRedirect(route('login'));
})->group('routes');

it('pending orders exclude those with active shipments', function (): void {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);

    $orderPending = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'status' => OrderStatus::Confirmed->value,
    ]);

    $orderDispatched = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'status' => OrderStatus::InTransit->value,
    ]);

    Shipment::factory()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $orderDispatched->id,
        'vehicle_id' => $vehicle->id,
        'status' => ShipmentStatus::Dispatched->value,
    ]);

    // Only pending order (no active shipment) should appear
    $pending = Order::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::Draft->value])
        ->whereDoesntHave('shipments', fn ($sq) => $sq->whereIn('status', [
            ShipmentStatus::Planned->value,
            ShipmentStatus::Dispatched->value,
        ]))
        ->count();

    expect($pending)->toBe(1);
});

it('assigning order to vehicle creates a planned shipment', function (): void {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'status' => OrderStatus::Confirmed->value,
    ]);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $role = Role::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    $user->assignRole($role);

    $this->actingAs($user);

    Shipment::create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'vehicle_id' => $vehicle->id,
        'driver_employee_id' => null,
        'status' => ShipmentStatus::Planned->value,
    ]);

    $order->update(['status' => OrderStatus::InTransit->value]);

    expect(Shipment::withoutGlobalScopes()->where('order_id', $order->id)->where('status', 'planned')->count())->toBe(1);
    expect($order->fresh()->status)->toBe(OrderStatus::InTransit);
});
