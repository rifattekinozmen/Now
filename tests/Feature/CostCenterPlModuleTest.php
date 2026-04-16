<?php

use App\Models\Employee;
use App\Models\FuelIntake;
use App\Models\FuelPrice;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.view', 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────
// ROUTE — page access
// ─────────────────────────────────────────────

it('admin can access cost center P&L page', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $this->actingAs($admin)
        ->get(route('admin.analytics.cost-centers'))
        ->assertSuccessful();
})->group('routes');

it('viewer can access cost center P&L page', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);
    $viewer->givePermissionTo('logistics.view');

    $this->actingAs($viewer)
        ->get(route('admin.analytics.cost-centers'))
        ->assertSuccessful();
})->group('routes');

it('unauthenticated user is redirected from cost center P&L page', function (): void {
    $this->get(route('admin.analytics.cost-centers'))
        ->assertRedirect();
})->group('routes');

// ─────────────────────────────────────────────
// DATA — computed rows render without error
// ─────────────────────────────────────────────

it('cost center page renders with shipment revenue data', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'freight_amount' => 5000.00,
        'currency_code' => 'TRY',
    ]);
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);

    Shipment::factory()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'vehicle_id' => $vehicle->id,
        'driver_employee_id' => $employee->id,
        'dispatched_at' => now()->subDays(10),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.analytics.cost-centers'))
        ->assertSuccessful();
})->group('data');

it('cost center page renders with fuel intake data', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);

    FuelIntake::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'liters' => 100,
        'recorded_at' => now()->subDays(5),
    ]);

    FuelPrice::factory()->create([
        'tenant_id' => $tenant->id,
        'price' => 40.00,
        'recorded_at' => now()->subDays(5)->toDateString(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.analytics.cost-centers'))
        ->assertSuccessful();
})->group('data');

// ─────────────────────────────────────────────
// TENANT ISOLATION
// ─────────────────────────────────────────────

it('cost center page does not expose another tenant vehicle data', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $adminA->givePermissionTo('logistics.admin');

    $vehicleB = Vehicle::factory()->create(['tenant_id' => $tenantB->id]);
    $orderB = Order::factory()->create([
        'tenant_id' => $tenantB->id,
        'freight_amount' => 9999.99,
    ]);
    $employeeB = Employee::factory()->create(['tenant_id' => $tenantB->id]);

    Shipment::factory()->create([
        'tenant_id' => $tenantB->id,
        'order_id' => $orderB->id,
        'vehicle_id' => $vehicleB->id,
        'driver_employee_id' => $employeeB->id,
        'dispatched_at' => now()->subDays(5),
    ]);

    $this->actingAs($adminA)
        ->get(route('admin.analytics.cost-centers'))
        ->assertSuccessful()
        ->assertDontSee($vehicleB->plate);
})->group('isolation');
