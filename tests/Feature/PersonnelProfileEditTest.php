<?php

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

it('employee can update their phone and email', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'phone' => '111',
        'email' => 'old@example.com',
    ]);
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::personnel.my-profile')
        ->call('openEditContact')
        ->set('editPhone', '+90 555 123 4567')
        ->set('editEmail', 'new@example.com')
        ->call('saveContact')
        ->assertHasNoErrors();

    $employee->refresh();
    expect($employee->phone)->toBe('+90 555 123 4567')
        ->and($employee->email)->toBe('new@example.com');
});

it('employee can clear phone and email', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'phone' => '111',
        'email' => 'old@example.com',
    ]);
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::personnel.my-profile')
        ->call('openEditContact')
        ->set('editPhone', '')
        ->set('editEmail', '')
        ->call('saveContact')
        ->assertHasNoErrors();

    $employee->refresh();
    expect($employee->phone)->toBeNull()
        ->and($employee->email)->toBeNull();
});

it('employee cannot set an invalid email', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::personnel.my-profile')
        ->call('openEditContact')
        ->set('editEmail', 'not-an-email')
        ->call('saveContact')
        ->assertHasErrors(['editEmail']);
});

it('employee cannot update another tenant employee', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $employeeA = Employee::factory()->create(['tenant_id' => $tenantA->id, 'phone' => 'original']);
    $employeeB = Employee::factory()->create(['tenant_id' => $tenantB->id]);

    $userB = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenantB->id,
        'employee_id' => $employeeB->id,
    ]);

    Livewire::actingAs($userB)
        ->test('pages::personnel.my-profile')
        ->call('openEditContact')
        ->set('editPhone', 'hacked')
        ->call('saveContact');

    // Employee A's phone must remain unchanged
    expect($employeeA->fresh()->phone)->toBe('original');
});
