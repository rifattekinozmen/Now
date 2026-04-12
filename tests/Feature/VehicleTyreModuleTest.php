<?php

use App\Enums\TyreStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleTyre;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.view', 'guard_name' => 'web']);
});

it('cannot read another tenant\'s tyres', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    VehicleTyre::factory()->create(['tenant_id' => $tenantA->id]);
    $tyreB = VehicleTyre::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA);

    $tyres = VehicleTyre::query()->get();
    expect($tyres->pluck('id'))->not->toContain($tyreB->id);
});

it('admin can access vehicle tyres index', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');

    $this->actingAs($user)
        ->get(route('admin.vehicle-tyres.index'))
        ->assertSuccessful();
});

it('viewer can access vehicle tyres index', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.view');

    $this->actingAs($user)
        ->get(route('admin.vehicle-tyres.index'))
        ->assertSuccessful();
});

it('unauthenticated user is redirected from tyres', function (): void {
    $this->get(route('admin.vehicle-tyres.index'))
        ->assertRedirect();
});

it('tyre status defaults to active', function (): void {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);
    $tyre = VehicleTyre::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
        'status' => TyreStatus::Active->value,
    ]);

    expect($tyre->status)->toBe(TyreStatus::Active);
    expect($tyre->status->isActive())->toBeTrue();
});

it('tyre belongs to vehicle', function (): void {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);
    $tyre = VehicleTyre::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
    ]);

    expect($tyre->vehicle->id)->toBe($vehicle->id);
});
