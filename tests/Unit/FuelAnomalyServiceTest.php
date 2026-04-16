<?php

use App\Models\AppNotification;
use App\Models\FuelIntake;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Logistics\FuelAnomalyService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// ─────────────────────────────────────────────
// FuelAnomalyService::analyze()
// ─────────────────────────────────────────────

test('no anomaly when fewer than two previous intakes exist', function (): void {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);

    // Only one prior intake — not enough for reference window
    FuelIntake::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'liters' => 50,
        'odometer_km' => 10000,
    ]);

    $intake = FuelIntake::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'liters' => 52,
        'odometer_km' => 10500,
    ]);

    $service = new FuelAnomalyService;
    $result = $service->analyze($intake);

    expect($result->isAnomaly)->toBeFalse();
});

test('no anomaly when consumption is within 15% threshold', function (): void {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);

    // Intake 1 → 2: 50 L over 500 km = 10 L/100km (reference)
    FuelIntake::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'liters' => 40,
        'odometer_km' => 9500,
    ]);
    FuelIntake::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'liters' => 50,
        'odometer_km' => 10000,
    ]);

    // Intake 2 → 3: 55 L over 500 km = 11 L/100km → +10% deviation (under threshold)
    $intake = FuelIntake::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'liters' => 55,
        'odometer_km' => 10500,
    ]);

    $service = new FuelAnomalyService;
    $result = $service->analyze($intake);

    expect($result->isAnomaly)->toBeFalse();
});

test('detects anomaly when consumption exceeds 15% threshold', function (): void {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);

    // Intake 1 → 2: 50 L over 500 km = 10 L/100km (reference)
    FuelIntake::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'liters' => 40,
        'odometer_km' => 9500,
    ]);
    FuelIntake::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'liters' => 50,
        'odometer_km' => 10000,
    ]);

    // Intake 2 → 3: 70 L over 500 km = 14 L/100km → +40% deviation (over threshold)
    $intake = FuelIntake::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'liters' => 70,
        'odometer_km' => 10500,
    ]);

    $service = new FuelAnomalyService;
    $result = $service->analyze($intake);

    expect($result->isAnomaly)->toBeTrue()
        ->and($result->deviationPercent)->toBeGreaterThan(15.0)
        ->and($result->currentConsumption)->toBeGreaterThan($result->referenceConsumption);
});

test('no anomaly when vehicles are different tenants', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $vehicleA = Vehicle::factory()->create(['tenant_id' => $tenantA->id]);
    $vehicleB = Vehicle::factory()->create(['tenant_id' => $tenantB->id]);

    // Tenant B has two prior intakes — but different vehicle than tenant A
    FuelIntake::factory()->create([
        'tenant_id' => $tenantB->id,
        'vehicle_id' => $vehicleB->id,
        'liters' => 40,
        'odometer_km' => 9500,
    ]);
    FuelIntake::factory()->create([
        'tenant_id' => $tenantB->id,
        'vehicle_id' => $vehicleB->id,
        'liters' => 50,
        'odometer_km' => 10000,
    ]);

    // Tenant A's first intake — should not use Tenant B's data
    $intake = FuelIntake::factory()->create([
        'tenant_id' => $tenantA->id,
        'vehicle_id' => $vehicleA->id,
        'liters' => 70,
        'odometer_km' => 10500,
    ]);

    $service = new FuelAnomalyService;
    $result = $service->analyze($intake);

    expect($result->isAnomaly)->toBeFalse();
});

test('notifyIfAnomaly creates AppNotification for tenant users when anomaly detected', function (): void {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);

    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'tenant-user', 'guard_name' => 'web'])
        ->givePermissionTo('logistics.admin');

    $adminUser = User::factory()->create(['tenant_id' => $tenant->id]);
    $adminUser->assignRole('tenant-user');

    // Reference intakes
    FuelIntake::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'liters' => 40,
        'odometer_km' => 9500,
    ]);
    FuelIntake::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'liters' => 50,
        'odometer_km' => 10000,
    ]);

    // Anomalous intake
    $intake = FuelIntake::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'liters' => 70,
        'odometer_km' => 10500,
    ]);

    $service = new FuelAnomalyService;
    $result = $service->notifyIfAnomaly($intake);

    expect($result->isAnomaly)->toBeTrue();
    expect(AppNotification::query()
        ->withoutGlobalScopes()
        ->where('user_id', $adminUser->id)
        ->where('type', 'fuel_anomaly')
        ->exists()
    )->toBeTrue();
});
