<?php

use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

// ─────────────────────────────────────────────
// BEHAVIOUR — tenant management (super-admin)
// ─────────────────────────────────────────────

it('non-super-admin cannot access tenants settings', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('tenants.edit'))
        ->assertForbidden();
})->group('behaviour');

it('super-admin can create a new company', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($superAdmin)
        ->test('pages::settings.tenants')
        ->set('newName', 'Yeni Lojistik A.Ş.')
        ->call('createCompany')
        ->assertHasNoErrors();

    expect(Tenant::where('name', 'Yeni Lojistik A.Ş.')->exists())->toBeTrue();
})->group('behaviour');

it('super-admin is auto-added to the newly created company', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($superAdmin)
        ->test('pages::settings.tenants')
        ->set('newName', 'Otomatik Üyelik A.Ş.')
        ->call('createCompany');

    $newTenant = Tenant::where('name', 'Otomatik Üyelik A.Ş.')->firstOrFail();
    expect($superAdmin->tenants()->where('tenant_id', $newTenant->id)->exists())->toBeTrue();
})->group('behaviour');

it('super-admin can archive a company', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create(['tenant_id' => $tenant->id]);
    $other = Tenant::factory()->create();

    Livewire::actingAs($superAdmin)
        ->test('pages::settings.tenants')
        ->call('archive', $other->id)
        ->assertHasNoErrors();

    expect($other->fresh()->isArchived())->toBeTrue();
})->group('behaviour');

it('super-admin can restore an archived company', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create(['tenant_id' => $tenant->id]);
    $other = Tenant::factory()->create(['archived_at' => now()]);

    Livewire::actingAs($superAdmin)
        ->test('pages::settings.tenants')
        ->call('restore', $other->id)
        ->assertHasNoErrors();

    expect($other->fresh()->isArchived())->toBeFalse();
})->group('behaviour');

it('super-admin can delete archived company with no users', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create(['tenant_id' => $tenant->id]);
    $empty = Tenant::factory()->create(['archived_at' => now()]);

    Livewire::actingAs($superAdmin)
        ->test('pages::settings.tenants')
        ->call('delete', $empty->id)
        ->assertHasNoErrors();

    expect(Tenant::find($empty->id))->toBeNull();
})->group('behaviour');

it('cannot delete non-archived company', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create(['tenant_id' => $tenant->id]);
    $active = Tenant::factory()->create();

    Livewire::actingAs($superAdmin)
        ->test('pages::settings.tenants')
        ->call('delete', $active->id)
        ->assertForbidden();

    expect(Tenant::find($active->id))->not->toBeNull();
})->group('behaviour');

it('super-admin can add a user to a company by email', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create(['tenant_id' => $tenant->id]);

    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

    Livewire::actingAs($superAdmin)
        ->test('pages::settings.tenants')
        ->call('startAddUser', $tenant->id)
        ->set('addUserEmail', $otherUser->email)
        ->call('addUser')
        ->assertHasNoErrors();

    expect($otherUser->tenants()->where('tenant_id', $tenant->id)->exists())->toBeTrue();
})->group('behaviour');

it('adding unknown email opens the name field for new user creation', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create(['tenant_id' => $tenant->id]);

    $component = Livewire::actingAs($superAdmin)
        ->test('pages::settings.tenants')
        ->call('startAddUser', $tenant->id)
        ->set('addUserEmail', 'nobody@nowhere.test')
        ->call('addUser')
        ->assertHasNoErrors();

    expect($component->get('addUserIsNew'))->toBeTrue();
})->group('behaviour');

it('super-admin cannot switch to an archived tenant', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create(['archived_at' => now()]);

    $user = User::factory()->superAdmin()->create(['tenant_id' => $tenantA->id]);
    $user->tenants()->syncWithoutDetaching([$tenantB->id]);

    $this->actingAs($user)
        ->post(route('tenant.switch', $tenantB))
        ->assertForbidden();
})->group('behaviour');
