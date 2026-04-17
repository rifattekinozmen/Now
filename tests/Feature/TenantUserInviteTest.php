<?php

use App\Authorization\LogisticsPermission;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    RolesAndPermissionsSeeder::ensureDefaults();
});

// ─────────────────────────────────────────────
// EXISTING USER — direct add
// ─────────────────────────────────────────────

it('adds an existing user to the company', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    $existing = User::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($superAdmin)
        ->test('pages::settings.tenants')
        ->set('addUserTenantId', $tenant->id)
        ->set('addUserEmail', $existing->email)
        ->call('addUser')
        ->assertHasNoErrors();

    expect($existing->fresh()->tenants->pluck('id'))->toContain($tenant->id);
})->group('behaviour');

// ─────────────────────────────────────────────
// UNKNOWN EMAIL — two-step flow
// ─────────────────────────────────────────────

it('opens the name field when email is not found', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();

    $component = Livewire::actingAs($superAdmin)
        ->test('pages::settings.tenants')
        ->set('addUserTenantId', $tenant->id)
        ->set('addUserEmail', 'newuser@example.com')
        ->call('addUser')
        ->assertHasNoErrors();

    expect($component->get('addUserIsNew'))->toBeTrue();
})->group('behaviour');

it('creates a new user and adds them to the company', function (): void {
    Notification::fake();

    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();

    Livewire::actingAs($superAdmin)
        ->test('pages::settings.tenants')
        ->set('addUserTenantId', $tenant->id)
        ->set('addUserEmail', 'newuser@example.com')
        ->set('addUserIsNew', true)
        ->set('addUserName', 'Yeni Kullanici')
        ->call('addUser')
        ->assertHasNoErrors();

    $user = User::where('email', 'newuser@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Yeni Kullanici')
        ->and($user->tenant_id)->toBe($tenant->id)
        ->and($user->tenants->pluck('id'))->toContain($tenant->id);
})->group('behaviour');

it('assigns ROLE_TENANT_USER to newly created user', function (): void {
    Notification::fake();

    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();

    Livewire::actingAs($superAdmin)
        ->test('pages::settings.tenants')
        ->set('addUserTenantId', $tenant->id)
        ->set('addUserEmail', 'invited@example.com')
        ->set('addUserIsNew', true)
        ->set('addUserName', 'Invited User')
        ->call('addUser');

    $user = User::where('email', 'invited@example.com')->first();

    expect($user->hasRole(RolesAndPermissionsSeeder::ROLE_TENANT_USER))->toBeTrue();
})->group('behaviour');

it('requires a name when creating a new user', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();

    Livewire::actingAs($superAdmin)
        ->test('pages::settings.tenants')
        ->set('addUserTenantId', $tenant->id)
        ->set('addUserEmail', 'newuser@example.com')
        ->set('addUserIsNew', true)
        ->set('addUserName', '')
        ->call('addUser')
        ->assertHasErrors(['addUserName']);
})->group('behaviour');

// ─────────────────────────────────────────────
// AUTHORIZATION
// ─────────────────────────────────────────────

it('non-super-admin cannot access companies settings page', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);

    $this->actingAs($admin)
        ->get(route('tenants.edit'))
        ->assertForbidden();
})->group('isolation');
