<?php

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;

// ─────────────────────────────────────────────
// ROUTE — access control
// ─────────────────────────────────────────────

it('employee can access their own profile page', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
    ]);

    $this->actingAs($user)
        ->get(route('personnel.profile'))
        ->assertSuccessful();
})->group('routes');

it('user without employee_id is forbidden from profile page', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('personnel.profile'))
        ->assertForbidden();
})->group('routes');

it('unauthenticated user is redirected from personnel profile', function (): void {
    $this->get(route('personnel.profile'))
        ->assertRedirect();
})->group('routes');
