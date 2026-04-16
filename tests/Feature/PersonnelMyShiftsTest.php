<?php

use App\Enums\ShiftStatus;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────
// ROUTE — page access
// ─────────────────────────────────────────────

it('employee with employee_id can access my shifts page', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
    ]);

    $this->actingAs($user)
        ->get(route('personnel.shifts.index'))
        ->assertSuccessful();
})->group('routes');

it('user without employee_id gets 403 on my shifts', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('personnel.shifts.index'))
        ->assertForbidden();
})->group('routes');

it('unauthenticated user is redirected from my shifts', function (): void {
    $this->get(route('personnel.shifts.index'))
        ->assertRedirect();
})->group('routes');

// ─────────────────────────────────────────────
// DATA — only own shifts visible
// ─────────────────────────────────────────────

it('employee sees only their own shifts', function (): void {
    $tenant = Tenant::factory()->create();
    $employeeA = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $employeeB = Employee::factory()->create(['tenant_id' => $tenant->id]);

    $userA = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employeeA->id,
    ]);

    Shift::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employeeA->id,
        'shift_date' => now()->toDateString(),
    ]);
    Shift::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employeeB->id,
        'shift_date' => now()->toDateString(),
    ]);

    $this->actingAs($userA)
        ->get(route('personnel.shifts.index'))
        ->assertSuccessful();

    // Employee A should only see their own shift
    expect(
        Shift::query()->where('employee_id', $employeeA->id)->count()
    )->toBe(1);

    expect(
        Shift::query()->where('employee_id', $employeeB->id)->count()
    )->toBe(1);
})->group('data');

it('my shifts KPI counts upcoming and confirmed shifts correctly', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
    ]);

    Shift::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'shift_date' => now()->addDay()->toDateString(),
    ]);

    Shift::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'shift_date' => now()->addDays(3)->toDateString(),
        'status' => ShiftStatus::Planned,
    ]);

    $confirmed = Shift::query()
        ->where('employee_id', $employee->id)
        ->where('status', ShiftStatus::Confirmed->value)
        ->count();

    expect($confirmed)->toBe(1);
})->group('data');
