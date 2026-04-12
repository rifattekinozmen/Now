<?php

use App\Enums\PayrollStatus;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.view', 'guard_name' => 'web']);
});

it('admin can print an approved payroll slip', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $payroll = Payroll::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'status' => PayrollStatus::Approved->value,
        'approved_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.hr.payroll.print', $payroll))
        ->assertSuccessful()
        ->assertViewIs('admin.payroll-print');
});

it('cannot print another tenant\'s payroll slip', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    $employeeB = Employee::factory()->create(['tenant_id' => $tenantB->id]);
    $payrollB = Payroll::factory()->create([
        'tenant_id' => $tenantB->id,
        'employee_id' => $employeeB->id,
        'status' => PayrollStatus::Approved->value,
        'approved_at' => now(),
    ]);

    // BelongsToTenant global scope returns 404 (not found) for cross-tenant records
    $this->actingAs($userA)
        ->get(route('admin.hr.payroll.print', $payrollB))
        ->assertNotFound();
});

it('unauthenticated user cannot access payroll print', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $payroll = Payroll::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'status' => PayrollStatus::Approved->value,
    ]);

    $this->get(route('admin.hr.payroll.print', $payroll))
        ->assertRedirect();
});
