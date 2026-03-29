<?php

use App\Enums\ShipmentStatus;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Services\Logistics\ShipmentDispatchComplianceGate;

test('gate allows planned shipment without vehicle', function () {
    $shipment = Shipment::factory()->create(['status' => ShipmentStatus::Planned, 'vehicle_id' => null]);

    $gate = new ShipmentDispatchComplianceGate;

    $gate->assertDispatchAllowed($shipment);

    expect(true)->toBeTrue();
});

test('gate allows shipment when vehicle inspection is in the future', function () {
    $customer = Customer::factory()->create();
    $order = Order::factory()->create(['customer_id' => $customer->id, 'tenant_id' => $customer->tenant_id]);
    $vehicle = Vehicle::factory()->create([
        'tenant_id' => $customer->tenant_id,
        'inspection_valid_until' => now()->addMonth(),
    ]);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'tenant_id' => $customer->tenant_id,
        'vehicle_id' => $vehicle->id,
        'status' => ShipmentStatus::Planned,
    ]);

    $gate = new ShipmentDispatchComplianceGate;

    $gate->assertDispatchAllowed($shipment);

    expect(true)->toBeTrue();
});

test('gate blocks when vehicle inspection date is missing', function () {
    $customer = Customer::factory()->create();
    $order = Order::factory()->create(['customer_id' => $customer->id, 'tenant_id' => $customer->tenant_id]);
    $vehicle = Vehicle::factory()->create([
        'tenant_id' => $customer->tenant_id,
        'inspection_valid_until' => null,
    ]);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'tenant_id' => $customer->tenant_id,
        'vehicle_id' => $vehicle->id,
        'status' => ShipmentStatus::Planned,
    ]);

    $gate = new ShipmentDispatchComplianceGate;

    $gate->assertDispatchAllowed($shipment);
})->throws(InvalidArgumentException::class);

test('gate blocks when vehicle inspection is expired', function () {
    $customer = Customer::factory()->create();
    $order = Order::factory()->create(['customer_id' => $customer->id, 'tenant_id' => $customer->tenant_id]);
    $vehicle = Vehicle::factory()->create([
        'tenant_id' => $customer->tenant_id,
        'inspection_valid_until' => now()->subDay(),
    ]);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'tenant_id' => $customer->tenant_id,
        'vehicle_id' => $vehicle->id,
        'status' => ShipmentStatus::Planned,
    ]);

    $gate = new ShipmentDispatchComplianceGate;

    $gate->assertDispatchAllowed($shipment);
})->throws(InvalidArgumentException::class);

test('gate blocks driver with expired license when meta references employee', function () {
    $customer = Customer::factory()->create();
    $order = Order::factory()->create(['customer_id' => $customer->id, 'tenant_id' => $customer->tenant_id]);
    $vehicle = Vehicle::factory()->create([
        'tenant_id' => $customer->tenant_id,
        'inspection_valid_until' => now()->addYear(),
    ]);
    $driver = Employee::factory()->create([
        'tenant_id' => $customer->tenant_id,
        'is_driver' => true,
        'license_valid_until' => now()->subWeek(),
    ]);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'tenant_id' => $customer->tenant_id,
        'vehicle_id' => $vehicle->id,
        'status' => ShipmentStatus::Planned,
        'meta' => ['driver_employee_id' => $driver->id],
    ]);

    $gate = new ShipmentDispatchComplianceGate;

    $gate->assertDispatchAllowed($shipment);
})->throws(InvalidArgumentException::class);
