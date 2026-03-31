<?php

use App\Enums\AttendanceStatus;
use App\Models\Employee;
use App\Models\PersonnelAttendance;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\PersonnelAttendancePolicy;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.employees.write', 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────
// TENANT ISOLATION
// ─────────────────────────────────────────────

it('hides other tenant attendance records via global scope', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    $employeeA = Employee::factory()->create(['tenant_id' => $tenantA->id]);
    $employeeB = Employee::factory()->create(['tenant_id' => $tenantB->id]);

    PersonnelAttendance::factory()->create([
        'tenant_id' => $tenantA->id,
        'employee_id' => $employeeA->id,
        'date' => now()->toDateString(),
    ]);

    $attendanceB = PersonnelAttendance::factory()->create([
        'tenant_id' => $tenantB->id,
        'employee_id' => $employeeB->id,
        'date' => now()->toDateString(),
    ]);

    $this->actingAs($userA);

    $ids = PersonnelAttendance::query()->pluck('id');
    expect($ids)->not->toContain($attendanceB->id);
})->group('isolation');

it('admin can see own tenant attendance on the page', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');
    $admin->givePermissionTo('logistics.view');

    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Deneme',
        'last_name' => 'Personel',
    ]);

    PersonnelAttendance::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'date' => now()->toDateString(),
        'status' => AttendanceStatus::Present->value,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.hr.attendance.index'))
        ->assertSuccessful();
})->group('isolation');

// ─────────────────────────────────────────────
// MAKER-CHECKER: approve policy
// ─────────────────────────────────────────────

it('non-admin cannot approve attendance via policy', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.view');
    $user->forgetCachedPermissions();
    $user = $user->fresh();

    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $attendance = PersonnelAttendance::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'date' => now()->toDateString(),
    ]);

    $policy = new PersonnelAttendancePolicy;
    expect($policy->approve($user, $attendance))->toBeFalse();
})->group('maker-checker');

it('logistics.admin can approve pending attendance via policy', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $attendance = PersonnelAttendance::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'date' => now()->toDateString(),
        'approved_at' => null,
    ]);

    $policy = new PersonnelAttendancePolicy;
    expect($policy->approve($admin, $attendance))->toBeTrue();
})->group('maker-checker');

it('already approved attendance cannot be approved again', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $attendance = PersonnelAttendance::factory()
        ->approved($admin)
        ->create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
        ]);

    $policy = new PersonnelAttendancePolicy;
    expect($policy->approve($admin, $attendance))->toBeFalse();
})->group('maker-checker');

it('admin from different tenant cannot approve', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $adminA->givePermissionTo('logistics.admin');

    $employeeB = Employee::factory()->create(['tenant_id' => $tenantB->id]);
    $attendance = PersonnelAttendance::factory()->create([
        'tenant_id' => $tenantB->id,
        'employee_id' => $employeeB->id,
        'date' => now()->toDateString(),
        'approved_at' => null,
    ]);

    $policy = new PersonnelAttendancePolicy;
    expect($policy->approve($adminA, $attendance))->toBeFalse();
})->group('maker-checker');

// ─────────────────────────────────────────────
// MODEL
// ─────────────────────────────────────────────

it('isApproved returns false when approved_at is null', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $attendance = PersonnelAttendance::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'date' => now()->toDateString(),
        'approved_at' => null,
    ]);

    expect($attendance->isApproved())->toBeFalse();
})->group('model');

it('isApproved returns true when approved_at is set', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);

    $attendance = PersonnelAttendance::factory()
        ->approved($admin)
        ->create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
        ]);

    expect($attendance->isApproved())->toBeTrue();
})->group('model');

it('scopeForDate returns only records for given date', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');
    $this->actingAs($admin);

    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);

    $todayRecord = PersonnelAttendance::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'date' => now()->toDateString(),
    ]);

    PersonnelAttendance::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'date' => now()->subDay()->toDateString(),
    ]);

    $result = PersonnelAttendance::query()->forDate(now()->toDateString())->get();
    expect($result->pluck('id')->all())->toContain($todayRecord->id)
        ->and($result)->toHaveCount(1);
})->group('model');
