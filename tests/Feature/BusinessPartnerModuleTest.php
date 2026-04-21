<?php

use App\Enums\BusinessPartnerType;
use App\Models\BusinessPartner;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Logistics\ExcelImportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.view', 'guard_name' => 'web']);
});

it('unauthenticated user is redirected from business partners index', function (): void {
    $this->get(route('admin.business-partners.index'))
        ->assertRedirect();
});

it('admin can access business partners index', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');

    $this->actingAs($user)
        ->get(route('admin.business-partners.index'))
        ->assertSuccessful();
});

it('viewer can access business partners index', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.view');

    $this->actingAs($user)
        ->get(route('admin.business-partners.index'))
        ->assertSuccessful();
});

it('business partners index is tenant scoped', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    BusinessPartner::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'Partner Alpha']);
    BusinessPartner::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Partner Beta']);

    $this->actingAs($userA)
        ->get(route('admin.business-partners.index'))
        ->assertSuccessful()
        ->assertSee('Partner Alpha')
        ->assertDontSee('Partner Beta');
});

it('business partners index clearPartnerAdvancedFilters resets type and status filters', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');

    $this->actingAs($user);

    Livewire::test('pages::admin.business-partners-index')
        ->set('filterType', BusinessPartnerType::Carrier->value)
        ->set('filterStatus', 'active')
        ->call('clearPartnerAdvancedFilters')
        ->assertSet('filterType', '')
        ->assertSet('filterStatus', '');
});

it('admin can create a business partner', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');

    $this->actingAs($user);

    Livewire::test('pages::admin.business-partners-index')
        ->set('name', 'Acme Logistics')
        ->set('type', BusinessPartnerType::Carrier->value)
        ->set('city', 'Istanbul')
        ->call('savePartner')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('business_partners', [
        'tenant_id' => $tenant->id,
        'name' => 'Acme Logistics',
        'type' => BusinessPartnerType::Carrier->value,
        'city' => 'Istanbul',
    ]);
});

it('admin can update a business partner', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');

    $partner = BusinessPartner::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Old Name',
        'type' => BusinessPartnerType::Supplier->value,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::admin.business-partners-index')
        ->call('startEdit', $partner->id)
        ->set('name', 'New Name')
        ->call('updatePartner')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('business_partners', [
        'id' => $partner->id,
        'name' => 'New Name',
    ]);
});

it('admin can delete a business partner', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');

    $partner = BusinessPartner::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user);

    Livewire::test('pages::admin.business-partners-index')
        ->call('deletePartner', $partner->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('business_partners', ['id' => $partner->id]);
});

it('non-admin cannot create a business partner', function (): void {
    RolesAndPermissionsSeeder::ensureDefaults();
    $user = User::factory()->logisticsViewer()->create();

    $this->actingAs($user);

    Livewire::test('pages::admin.business-partners-index')
        ->set('name', 'Unauthorized Partner')
        ->set('type', BusinessPartnerType::Broker->value)
        ->call('savePartner')
        ->assertForbidden();
});

it('business partner factory creates valid records', function (): void {
    $tenant = Tenant::factory()->create();
    $partner = BusinessPartner::factory()->create(['tenant_id' => $tenant->id]);

    expect($partner->name)->not->toBeEmpty();
    expect($partner->type)->toBeInstanceOf(BusinessPartnerType::class);
    expect($partner->is_active)->toBeTrue();
});

it('business partner inactive state works', function (): void {
    $tenant = Tenant::factory()->create();
    $partner = BusinessPartner::factory()->inactive()->create(['tenant_id' => $tenant->id]);

    expect($partner->is_active)->toBeFalse();
});

it('business partner type enum has correct colors', function (): void {
    expect(BusinessPartnerType::Carrier->color())->toBe('blue');
    expect(BusinessPartnerType::Supplier->color())->toBe('green');
    expect(BusinessPartnerType::Broker->color())->toBe('purple');
    expect(BusinessPartnerType::CustomsAgent->color())->toBe('orange');
    expect(BusinessPartnerType::Other->color())->toBe('zinc');
});

it('business partner excel import service has correct mapping', function (): void {
    $service = new ExcelImportService;
    $mapping = $service->getBusinessPartnerImportMapping();

    expect($mapping)->toHaveKey('İsim');
    expect($mapping['İsim'])->toBe('name');
    expect($mapping)->toHaveKey('Tip');
    expect($mapping['Tip'])->toBe('type');
    expect($mapping)->toHaveKey('IBAN');
    expect($mapping['IBAN'])->toBe('iban');
});
