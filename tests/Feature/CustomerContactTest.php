<?php

use App\Authorization\LogisticsPermission;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => LogisticsPermission::ADMIN, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => LogisticsPermission::VIEW, 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────
// ROUTE — page access
// ─────────────────────────────────────────────

it('admin can access customer contacts tab', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);

    $this->actingAs($admin)
        ->get(route('admin.customers.show', $customer))
        ->assertSuccessful();
})->group('routes');

// ─────────────────────────────────────────────
// BEHAVIOUR — CRUD
// ─────────────────────────────────────────────

it('can create a customer contact', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);

    Livewire::actingAs($admin)
        ->test('pages::admin.customer-show', ['customer' => $customer])
        ->call('openContactForm', null)
        ->set('ctName', 'Ali Veli')
        ->set('ctPosition', 'Satın Alma')
        ->set('ctPhone', '05001234567')
        ->set('ctEmail', 'ali@example.com')
        ->set('ctIsPrimary', true)
        ->call('saveContact')
        ->assertSet('showContactForm', false);

    $this->assertDatabaseHas('customer_contacts', [
        'customer_id' => $customer->id,
        'tenant_id' => $tenant->id,
        'name' => 'Ali Veli',
        'position' => 'Satın Alma',
        'is_primary' => true,
    ]);
})->group('behaviour');

it('requires name when saving contact', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);

    Livewire::actingAs($admin)
        ->test('pages::admin.customer-show', ['customer' => $customer])
        ->call('openContactForm', null)
        ->set('ctName', '')
        ->call('saveContact')
        ->assertHasErrors(['ctName' => 'required']);
})->group('behaviour');

it('can edit a customer contact', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $contact = CustomerContact::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'name' => 'Old Name',
    ]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);

    Livewire::actingAs($admin)
        ->test('pages::admin.customer-show', ['customer' => $customer])
        ->call('openContactForm', $contact->id)
        ->assertSet('ctName', 'Old Name')
        ->set('ctName', 'New Name')
        ->call('saveContact');

    $this->assertDatabaseHas('customer_contacts', [
        'id' => $contact->id,
        'name' => 'New Name',
    ]);
})->group('behaviour');

it('can delete a customer contact', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $contact = CustomerContact::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
    ]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);

    Livewire::actingAs($admin)
        ->test('pages::admin.customer-show', ['customer' => $customer])
        ->call('deleteContact', $contact->id);

    $this->assertDatabaseMissing('customer_contacts', ['id' => $contact->id]);
})->group('behaviour');

// ─────────────────────────────────────────────
// ISOLATION — tenant security
// ─────────────────────────────────────────────

it('cannot delete contact belonging to another tenant', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $adminA->givePermissionTo(LogisticsPermission::ADMIN);

    $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);
    $contactB = CustomerContact::factory()->create([
        'tenant_id' => $tenantB->id,
        'customer_id' => $customerB->id,
    ]);

    $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);

    Livewire::actingAs($adminA)
        ->test('pages::admin.customer-show', ['customer' => $customerA])
        ->call('deleteContact', $contactB->id);

    $this->assertDatabaseHas('customer_contacts', ['id' => $contactB->id]);
})->group('behaviour');
