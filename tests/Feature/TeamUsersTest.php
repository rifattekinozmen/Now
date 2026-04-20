<?php

use App\Authorization\LogisticsPermission;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => LogisticsPermission::ADMIN, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => LogisticsPermission::VIEW, 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────
// ROUTE — page access
// ─────────────────────────────────────────────

it('admin can access team page', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);

    $this->actingAs($admin)
        ->get(route('admin.team.index'))
        ->assertSuccessful();
})->group('routes');

it('non-admin is forbidden from team page', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('admin.team.index'))
        ->assertForbidden();
})->group('routes');

it('unauthenticated user is redirected from team page', function (): void {
    $this->get(route('admin.team.index'))
        ->assertRedirect();
})->group('routes');

// ─────────────────────────────────────────────
// TENANT ISOLATION
// ─────────────────────────────────────────────

it('admin only sees users from their own tenant', function (): void {
    RolesAndPermissionsSeeder::ensureDefaults();

    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $adminA->givePermissionTo(LogisticsPermission::ADMIN);

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userB = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($adminA);

    $component = Livewire::test('pages::admin.team-users-index');

    $ids = $component->get('teamUsers')->pluck('id')->toArray();
    expect($ids)->toContain($adminA->id)
        ->toContain($userA->id)
        ->not->toContain($userB->id);
})->group('behaviour');

it('admin sees users for the active tenant when primary tenant_id differs', function (): void {
    RolesAndPermissionsSeeder::ensureDefaults();

    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $admin = User::factory()->create(['tenant_id' => $tenantA->id]);
    $admin->tenants()->syncWithoutDetaching([$tenantA->id, $tenantB->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);
    $admin->update(['active_tenant_id' => $tenantB->id]);

    $userOnlyInB = User::factory()->create(['tenant_id' => $tenantB->id]);
    $userOnlyInA = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenantA->id]);

    $this->actingAs($admin);

    $component = Livewire::test('pages::admin.team-users-index');

    $ids = $component->get('teamUsers')->pluck('id')->toArray();
    expect($ids)->toContain($userOnlyInB->id)
        ->not->toContain($userOnlyInA->id);
})->group('behaviour');

// ─────────────────────────────────────────────
// BEHAVIOUR — role changes
// ─────────────────────────────────────────────

it('admin can make another user a viewer', function (): void {
    RolesAndPermissionsSeeder::ensureDefaults();

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);

    // Use factory state so target only has the tenant-user role (no extra direct perms)
    $target = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.team-users-index')
        ->call('makeViewer', $target->id)
        ->assertHasNoErrors();

    $target->refresh()->unsetRelations();
    expect($target->can(LogisticsPermission::ADMIN))->toBeFalse();
    expect($target->can(LogisticsPermission::VIEW))->toBeTrue();
})->group('behaviour');

it('admin cannot change their own role — flash message is shown', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);

    $this->actingAs($admin);

    Livewire::test('pages::admin.team-users-index')
        ->call('makeViewer', $admin->id)
        ->assertSet('flashMessage', __('You cannot change your own role.'));

    // Admin role must be unchanged
    $admin->refresh()->unsetRelations();
    expect($admin->can(LogisticsPermission::ADMIN))->toBeTrue();
})->group('behaviour');

it('admin cannot change roles of users in other tenants', function (): void {
    RolesAndPermissionsSeeder::ensureDefaults();

    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $adminA->givePermissionTo(LogisticsPermission::ADMIN);

    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
    $userB->givePermissionTo(LogisticsPermission::ADMIN);

    $this->actingAs($adminA);

    $this->expectException(ModelNotFoundException::class);

    Livewire::test('pages::admin.team-users-index')
        ->call('makeViewer', $userB->id);
})->group('behaviour');
