<?php

use App\Enums\VehicleFineStatus;
use App\Enums\VehicleFineType;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\VehicleFine;

test('vehicle fine can be created with all fields', function () {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);

    $fine = VehicleFine::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'fine_type' => VehicleFineType::Speeding->value,
        'status' => VehicleFineStatus::Pending->value,
        'amount' => 1500.00,
    ]);

    expect($fine->fine_type)->toBe(VehicleFineType::Speeding)
        ->and($fine->status)->toBe(VehicleFineStatus::Pending)
        ->and((float) $fine->amount)->toBe(1500.0);
});

test('vehicle fine status can be transitioned to paid', function () {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);

    $fine = VehicleFine::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'status' => VehicleFineStatus::Pending->value,
    ]);

    $fine->update(['status' => VehicleFineStatus::Paid->value, 'paid_at' => now()]);

    expect($fine->fresh()->status)->toBe(VehicleFineStatus::Paid)
        ->and($fine->fresh()->paid_at)->not->toBeNull();
});

test('vehicle fine is accessible via vehicle relation', function () {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);

    VehicleFine::factory()->count(3)->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
    ]);

    expect($vehicle->vehicleFines()->count())->toBe(3);
});

test('vehicle fine factory paid state works', function () {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);

    $fine = VehicleFine::factory()->paid()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
    ]);

    expect($fine->status)->toBe(VehicleFineStatus::Paid)
        ->and($fine->paid_at)->not->toBeNull();
});

test('vehicle fines are tenant-isolated', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    $vehicle1 = Vehicle::factory()->create(['tenant_id' => $tenant1->id]);
    $vehicle2 = Vehicle::factory()->create(['tenant_id' => $tenant2->id]);

    VehicleFine::factory()->create(['tenant_id' => $tenant1->id, 'vehicle_id' => $vehicle1->id]);
    VehicleFine::factory()->create(['tenant_id' => $tenant2->id, 'vehicle_id' => $vehicle2->id]);

    $tenant1Fines = VehicleFine::withoutGlobalScopes()->where('tenant_id', $tenant1->id)->count();
    $tenant2Fines = VehicleFine::withoutGlobalScopes()->where('tenant_id', $tenant2->id)->count();

    expect($tenant1Fines)->toBe(1)
        ->and($tenant2Fines)->toBe(1);
});

test('vehicle fine enum type labels return non-empty strings', function () {
    expect(VehicleFineType::Speeding->label())->toBeString()->not->toBeEmpty();
    expect(VehicleFineType::Overload->label())->toBeString()->not->toBeEmpty();
    expect(VehicleFineStatus::Pending->label())->toBeString()->not->toBeEmpty();
    expect(VehicleFineStatus::Paid->label())->toBeString()->not->toBeEmpty();
    expect(VehicleFineStatus::Appealed->label())->toBeString()->not->toBeEmpty();
    // All cases covered
    expect(VehicleFineType::cases())->toHaveCount(5);
    expect(VehicleFineStatus::cases())->toHaveCount(3);
});

test('vehicle fine with appealed status', function () {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);

    $fine = VehicleFine::factory()->appealed()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
    ]);

    expect($fine->status)->toBe(VehicleFineStatus::Appealed);
});
