<?php

use App\Authorization\LogisticsPermission;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    RolesAndPermissionsSeeder::ensureDefaults();
});

// ─────────────────────────────────────────────
// ACCESS — only admin can open company settings
// ─────────────────────────────────────────────

it('admin can access company settings team tab', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);

    Livewire::actingAs($admin)
        ->test('pages::settings.company')
        ->set('tab', 'team')
        ->assertHasNoErrors();
})->group('behaviour');

it('non-admin cannot open company settings', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->logisticsViewer()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('company.edit'))
        ->assertForbidden();
})->group('behaviour');

// ─────────────────────────────────────────────
// ROLE ASSIGNMENT
// ─────────────────────────────────────────────

it('admin can assign viewer role to a team member', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);
    $member = User::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test('pages::settings.company')
        ->call('assignRole', $member->id, 'viewer')
        ->assertHasNoErrors();

    expect($member->fresh()->hasRole(RolesAndPermissionsSeeder::ROLE_LOGISTICS_VIEWER))->toBeTrue();
})->group('behaviour');

it('admin can assign order-clerk role to a team member', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);
    $member = User::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test('pages::settings.company')
        ->call('assignRole', $member->id, 'order-clerk')
        ->assertHasNoErrors();

    expect($member->fresh()->hasRole(RolesAndPermissionsSeeder::ROLE_LOGISTICS_ORDER_CLERK))->toBeTrue();
})->group('behaviour');

it('admin can assign hr role to a team member', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);
    $member = User::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test('pages::settings.company')
        ->call('assignRole', $member->id, 'hr')
        ->assertHasNoErrors();

    expect($member->fresh()->hasRole(RolesAndPermissionsSeeder::ROLE_LOGISTICS_HR))->toBeTrue();
})->group('behaviour');

it('admin can remove access from a team member', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);
    $member = User::factory()->create(['tenant_id' => $tenant->id]);
    $member->assignRole(RolesAndPermissionsSeeder::ROLE_LOGISTICS_VIEWER);

    Livewire::actingAs($admin)
        ->test('pages::settings.company')
        ->call('assignRole', $member->id, 'none')
        ->assertHasNoErrors();

    expect($member->fresh()->roles->isEmpty())->toBeTrue();
})->group('behaviour');

it('admin cannot change their own role', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);

    Livewire::actingAs($admin)
        ->test('pages::settings.company')
        ->call('assignRole', $admin->id, 'viewer')
        ->assertHasNoErrors();

    expect($admin->fresh()->can(LogisticsPermission::ADMIN))->toBeTrue();
})->group('behaviour');

// ─────────────────────────────────────────────
// TENANT ISOLATION
// ─────────────────────────────────────────────

it('cannot assign role to a user from another tenant', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $adminA->givePermissionTo(LogisticsPermission::ADMIN);
    $memberB = User::factory()->create(['tenant_id' => $tenantB->id]);

    Livewire::actingAs($adminA)
        ->test('pages::settings.company')
        ->call('assignRole', $memberB->id, 'viewer')
        ->assertForbidden();
})->group('isolation');
