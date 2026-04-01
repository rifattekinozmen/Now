<?php

use App\Enums\ExpenseType;
use App\Models\Tenant;
use App\Models\TripExpense;
use App\Models\User;
use App\Models\Vehicle;
use App\Policies\TripExpensePolicy;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.trip-expenses.write', 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────
// TENANT ISOLATION
// ─────────────────────────────────────────────

it('cannot read another tenant\'s trip expenses', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    $vehicleA = Vehicle::factory()->create(['tenant_id' => $tenantA->id]);
    $vehicleB = Vehicle::factory()->create(['tenant_id' => $tenantB->id]);

    $expA = TripExpense::factory()->create(['tenant_id' => $tenantA->id, 'vehicle_id' => $vehicleA->id]);
    $expB = TripExpense::factory()->create(['tenant_id' => $tenantB->id, 'vehicle_id' => $vehicleB->id]);

    $this->actingAs($userA);

    $expenses = TripExpense::query()->get();
    expect($expenses->pluck('id'))->not->toContain($expB->id)
        ->and($expenses->pluck('id'))->toContain($expA->id);
})->group('isolation');

// ─────────────────────────────────────────────
// ROUTE ACCESS
// ─────────────────────────────────────────────

it('admin can access the trip expenses page', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $this->actingAs($admin)
        ->get(route('admin.trip-expenses.index'))
        ->assertSuccessful();
})->group('route');

it('unauthenticated user is redirected from trip expenses page', function (): void {
    $this->get(route('admin.trip-expenses.index'))
        ->assertRedirect();
})->group('route');

// ─────────────────────────────────────────────
// POLICY
// ─────────────────────────────────────────────

it('user with write permission can create trip expenses via policy', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.trip-expenses.write');

    $policy = new TripExpensePolicy;
    expect($policy->create($user->fresh()))->toBeTrue();
})->group('policy');

it('only logistics admin can delete a trip expense', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');
    $writer = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);
    $writer->givePermissionTo('logistics.trip-expenses.write');

    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);
    $expense = TripExpense::factory()->create(['tenant_id' => $tenant->id, 'vehicle_id' => $vehicle->id]);

    $policy = new TripExpensePolicy;
    expect($policy->delete($admin, $expense))->toBeTrue()
        ->and($policy->delete($writer->fresh(), $expense))->toBeFalse();
})->group('policy');

// ─────────────────────────────────────────────
// VEHICLE SHOW — EXPENSES TAB
// ─────────────────────────────────────────────

it('trip expenses are scoped correctly for vehicle show expenses tab', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);
    TripExpense::factory()->count(3)->create(['tenant_id' => $tenant->id, 'vehicle_id' => $vehicle->id]);

    $this->actingAs($admin);

    $expenses = TripExpense::query()->where('vehicle_id', $vehicle->id)->get();
    expect($expenses)->toHaveCount(3)
        ->and($expenses->pluck('vehicle_id')->unique()->first())->toBe($vehicle->id);
})->group('vehicle-show');

it('vehicle show expenses tab only shows expenses for that vehicle', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $vehicleA = Vehicle::factory()->create(['tenant_id' => $tenant->id]);
    $vehicleB = Vehicle::factory()->create(['tenant_id' => $tenant->id]);

    $expA = TripExpense::factory()->create(['tenant_id' => $tenant->id, 'vehicle_id' => $vehicleA->id]);
    TripExpense::factory()->create(['tenant_id' => $tenant->id, 'vehicle_id' => $vehicleB->id]);

    $this->actingAs($admin);

    $expenses = TripExpense::query()->where('vehicle_id', $vehicleA->id)->get();
    expect($expenses)->toHaveCount(1)
        ->and($expenses->first()->id)->toBe($expA->id);
})->group('vehicle-show');

// ─────────────────────────────────────────────
// FACTORY STATES
// ─────────────────────────────────────────────

it('fuel() factory state creates a fuel expense', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($user);

    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);
    $expense = TripExpense::factory()->fuel()->create(['tenant_id' => $tenant->id, 'vehicle_id' => $vehicle->id]);

    expect($expense->expense_type)->toBe(ExpenseType::Fuel);
})->group('factory');
