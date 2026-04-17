<?php

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

// ─────────────────────────────────────────────
// BEHAVIOUR — employee edit tab
// ─────────────────────────────────────────────

it('admin can update personal fields on employee show', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);

    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Ali',
        'last_name' => 'Veli',
        'phone' => null,
        'email' => null,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.employee-show', ['id' => $employee->id])
        ->set('editFirstName', 'Mehmet')
        ->set('editLastName', 'Yılmaz')
        ->set('editPhone', '+90 555 111 22 33')
        ->set('editEmail', 'mehmet@example.com')
        ->call('saveEmployee')
        ->assertHasNoErrors();

    $employee->refresh();
    expect($employee->first_name)->toBe('Mehmet');
    expect($employee->last_name)->toBe('Yılmaz');
    expect($employee->phone)->toBe('+90 555 111 22 33');
    expect($employee->email)->toBe('mehmet@example.com');
})->group('behaviour');

it('admin can set driver credentials on employee show', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);

    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'is_driver' => false,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.employee-show', ['id' => $employee->id])
        ->set('editIsDriver', true)
        ->set('editLicenseClass', 'CE')
        ->set('editLicenseValidUntil', '2027-12-31')
        ->set('editSrcValidUntil', '2026-06-30')
        ->call('saveEmployee')
        ->assertHasNoErrors();

    $employee->refresh();
    expect($employee->is_driver)->toBeTrue();
    expect($employee->license_class)->toBe('CE');
    expect($employee->license_valid_until->format('Y-m-d'))->toBe('2027-12-31');
    expect($employee->src_valid_until->format('Y-m-d'))->toBe('2026-06-30');
})->group('behaviour');

it('non-admin cannot update employee', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->logisticsViewer()->create(['tenant_id' => $tenant->id]);

    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Original',
    ]);

    Livewire::actingAs($viewer)
        ->test('pages::admin.employee-show', ['id' => $employee->id])
        ->set('editFirstName', 'Changed')
        ->call('saveEmployee');

    $employee->refresh();
    expect($employee->first_name)->toBe('Original');
})->group('behaviour');

it('first name is required on employee save', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test('pages::admin.employee-show', ['id' => $employee->id])
        ->set('editFirstName', '')
        ->call('saveEmployee')
        ->assertHasErrors(['editFirstName' => 'required']);
})->group('behaviour');
