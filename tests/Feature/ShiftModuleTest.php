<?php

use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.shifts.write', 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────
// MODEL — casts and factory states
// ─────────────────────────────────────────────

it('shift factory creates a valid planned record', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);

    $shift = Shift::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
    ]);

    expect($shift->status)->toBe(ShiftStatus::Planned)
        ->and($shift->shift_type)->toBe(ShiftType::Regular)
        ->and($shift->shift_date)->not->toBeNull();
});

it('confirmed factory state sets status to confirmed', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);

    $shift = Shift::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
    ]);

    expect($shift->status)->toBe(ShiftStatus::Confirmed);
});

// ─────────────────────────────────────────────
// ROUTE — page access
// ─────────────────────────────────────────────

it('admin can access shifts index page', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $this->actingAs($admin)
        ->get(route('admin.hr.shifts.index'))
        ->assertSuccessful();
})->group('routes');

it('viewer can access shifts index page', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);
    $viewer->givePermissionTo('logistics.view');

    $this->actingAs($viewer)
        ->get(route('admin.hr.shifts.index'))
        ->assertSuccessful();
})->group('routes');

it('unauthenticated user is redirected from shifts index', function (): void {
    $this->get(route('admin.hr.shifts.index'))
        ->assertRedirect();
})->group('routes');

// ─────────────────────────────────────────────
// TENANT ISOLATION
// ─────────────────────────────────────────────

it('cannot see another tenant\'s shifts via global scope', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $employeeB = Employee::factory()->create(['tenant_id' => $tenantB->id]);
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    $shiftB = Shift::factory()->create([
        'tenant_id' => $tenantB->id,
        'employee_id' => $employeeB->id,
    ]);

    $this->actingAs($userA);

    $found = Shift::query()->where('id', $shiftB->id)->first();
    expect($found)->toBeNull();
})->group('isolation');

// ─────────────────────────────────────────────
// POLICY — write permission
// ─────────────────────────────────────────────

it('viewer cannot delete a shift', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);
    $viewer->givePermissionTo('logistics.view');
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);

    $shift = Shift::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
    ]);

    $this->actingAs($viewer);

    expect($viewer->can('delete', $shift))->toBeFalse();
})->group('policy');

it('admin can delete a shift', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);

    $shift = Shift::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
    ]);

    $this->actingAs($admin);

    expect($admin->can('delete', $shift))->toBeTrue();
})->group('policy');
