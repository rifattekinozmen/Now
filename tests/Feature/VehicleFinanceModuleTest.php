<?php

use App\Enums\VehicleFinanceType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleFinance;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.view', 'guard_name' => 'web']);
});

it('cannot read another tenant\'s vehicle finances', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    VehicleFinance::factory()->create(['tenant_id' => $tenantA->id]);
    $finB = VehicleFinance::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA);

    $records = VehicleFinance::query()->get();
    expect($records->pluck('id'))->not->toContain($finB->id);
});

it('admin can access vehicle finances index', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');

    $this->actingAs($user)
        ->get(route('admin.vehicle-finances.index'))
        ->assertSuccessful();
});

it('viewer can access vehicle finances index', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.view');

    $this->actingAs($user)
        ->get(route('admin.vehicle-finances.index'))
        ->assertSuccessful();
});

it('unauthenticated user is redirected from vehicle finances', function (): void {
    $this->get(route('admin.vehicle-finances.index'))
        ->assertRedirect();
});

it('vehicle finance has correct enum cast', function (): void {
    $tenant = Tenant::factory()->create();
    $vf = VehicleFinance::factory()->create([
        'tenant_id' => $tenant->id,
        'finance_type' => VehicleFinanceType::Insurance->value,
    ]);

    expect($vf->fresh()->finance_type)->toBe(VehicleFinanceType::Insurance);
    expect($vf->finance_type->label())->toBe(__('Insurance'));
    expect($vf->finance_type->color())->toBe('blue');
});

it('vehicle finance belongs to vehicle', function (): void {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);
    $vf = VehicleFinance::factory()->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
    ]);

    expect($vf->vehicle->id)->toBe($vehicle->id);
});

it('vehicle has many vehicle finances', function (): void {
    $tenant = Tenant::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);
    VehicleFinance::factory()->count(3)->create([
        'tenant_id' => $tenant->id,
        'vehicle_id' => $vehicle->id,
    ]);

    expect($vehicle->vehicleFinances)->toHaveCount(3);
});

it('unpaid factory state sets paid_at to null', function (): void {
    $tenant = Tenant::factory()->create();
    $vf = VehicleFinance::factory()->unpaid()->create(['tenant_id' => $tenant->id]);

    expect($vf->paid_at)->toBeNull();
    expect($vf->due_date)->not->toBeNull();
});
