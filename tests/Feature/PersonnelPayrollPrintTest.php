<?php

use App\Enums\PayrollStatus;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Tenant;
use App\Models\User;

// ─────────────────────────────────────────────
// ROUTE — access control
// ─────────────────────────────────────────────

it('employee can print their own approved payroll', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
    ]);

    $payroll = Payroll::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'status' => PayrollStatus::Approved,
    ]);

    $this->actingAs($user)
        ->get(route('personnel.payrolls.print', $payroll))
        ->assertSuccessful();
})->group('routes');

it('employee cannot print another employee payroll', function (): void {
    $tenant = Tenant::factory()->create();
    $employeeA = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $employeeB = Employee::factory()->create(['tenant_id' => $tenant->id]);

    $userA = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employeeA->id,
    ]);

    $payrollB = Payroll::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employeeB->id,
        'status' => PayrollStatus::Approved,
    ]);

    $this->actingAs($userA)
        ->get(route('personnel.payrolls.print', $payrollB))
        ->assertForbidden();
})->group('routes');

it('employee cannot print a draft payroll', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
    ]);

    $payroll = Payroll::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'status' => PayrollStatus::Draft,
    ]);

    $this->actingAs($user)
        ->get(route('personnel.payrolls.print', $payroll))
        ->assertForbidden();
})->group('routes');

it('unauthenticated user is redirected from personnel payroll print', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);

    $payroll = Payroll::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'status' => PayrollStatus::Approved,
    ]);

    $this->get(route('personnel.payrolls.print', $payroll))
        ->assertRedirect();
})->group('routes');
