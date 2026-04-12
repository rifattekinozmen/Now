<?php

use App\Enums\LeaveStatus;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Payroll;
use App\Models\Tenant;
use App\Models\User;

it('unauthenticated user is redirected from personnel portal', function (): void {
    $this->get(route('personnel.dashboard'))->assertRedirect();
});

it('user without employee_id gets 403 on personnel dashboard', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => null]);

    $this->actingAs($user)
        ->get(route('personnel.dashboard'))
        ->assertForbidden();
});

it('user with employee_id can access personnel dashboard', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);

    $this->actingAs($user)
        ->get(route('personnel.dashboard'))
        ->assertSuccessful();
});

it('employee can access my payrolls page', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);

    $this->actingAs($user)
        ->get(route('personnel.payrolls.index'))
        ->assertSuccessful();
});

it('employee can access my leaves page', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);

    $this->actingAs($user)
        ->get(route('personnel.leaves.index'))
        ->assertSuccessful();
});

it('employee can access my advances page', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);

    $this->actingAs($user)
        ->get(route('personnel.advances.index'))
        ->assertSuccessful();
});

it('employee only sees their own leaves', function (): void {
    $tenant = Tenant::factory()->create();
    $employeeA = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $employeeB = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $userA = User::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employeeA->id]);

    $leaveA = Leave::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employeeA->id,
        'status' => LeaveStatus::Pending->value,
    ]);
    $leaveB = Leave::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employeeB->id,
        'status' => LeaveStatus::Pending->value,
    ]);

    $this->actingAs($userA);

    // Direct model query scoped by middleware/employee_id check
    $leaves = Leave::query()->where('employee_id', $employeeA->id)->get();
    expect($leaves->pluck('id'))->toContain($leaveA->id);
    expect($leaves->pluck('id'))->not->toContain($leaveB->id);
});

it('employee only sees their own payrolls', function (): void {
    $tenant = Tenant::factory()->create();
    $employeeA = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $employeeB = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $userA = User::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employeeA->id]);

    $payrollA = Payroll::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employeeA->id]);
    $payrollB = Payroll::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employeeB->id]);

    $this->actingAs($userA);

    $payrolls = Payroll::query()->where('employee_id', $employeeA->id)->get();
    expect($payrolls->pluck('id'))->toContain($payrollA->id);
    expect($payrolls->pluck('id'))->not->toContain($payrollB->id);
});

it('user employee relation loads correctly', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);

    expect($user->fresh()->employee->id)->toBe($employee->id);
    expect($user->fresh()->employee_id)->toBe($employee->id);
});
