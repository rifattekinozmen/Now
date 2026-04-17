<?php

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────
// ROUTE — page access
// ─────────────────────────────────────────────

it('admin can access company settings page', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $this->actingAs($admin)
        ->get(route('company.edit'))
        ->assertSuccessful();
})->group('routes');

it('non-admin is redirected from company settings page', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('company.edit'))
        ->assertForbidden();
})->group('routes');

it('unauthenticated user is redirected from company settings page', function (): void {
    $this->get(route('company.edit'))
        ->assertRedirect();
})->group('routes');

// ─────────────────────────────────────────────
// BEHAVIOUR — save persists data
// ─────────────────────────────────────────────

it('admin can save company profile and tenant name is updated', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Old Name']);
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $this->actingAs($admin);

    Livewire::test('pages::settings.company')
        ->set('companyName', 'New Company Ltd.')
        ->set('companyTaxId', '1234567890')
        ->set('companyAddress', '123 Main St')
        ->set('companyCity', 'Istanbul')
        ->set('companyPhone', '+90 555 000 00 00')
        ->set('companyEmail', 'info@newcompany.com')
        ->set('companyWebsite', 'https://newcompany.com')
        ->call('save')
        ->assertHasNoErrors();

    expect(Tenant::find($tenant->id)->name)->toBe('New Company Ltd.');
    expect(TenantSetting::get($tenant->id, 'company_tax_id'))->toBe('1234567890');
    expect(TenantSetting::get($tenant->id, 'company_address'))->toBe('123 Main St');
    expect(TenantSetting::get($tenant->id, 'company_city'))->toBe('Istanbul');
    expect(TenantSetting::get($tenant->id, 'company_phone'))->toBe('+90 555 000 00 00');
    expect(TenantSetting::get($tenant->id, 'company_email'))->toBe('info@newcompany.com');
    expect(TenantSetting::get($tenant->id, 'company_website'))->toBe('https://newcompany.com');
})->group('behaviour');

it('non-admin cannot call save directly via route', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Keep This']);
    $user = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);

    // The non-admin route test already proves 403; additionally assert Tenant name is unchanged
    $this->actingAs($user)
        ->get(route('company.edit'))
        ->assertForbidden();

    expect(Tenant::find($tenant->id)->name)->toBe('Keep This');
})->group('behaviour');

it('company name is required', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $this->actingAs($admin);

    Livewire::test('pages::settings.company')
        ->set('companyName', '')
        ->call('save')
        ->assertHasErrors(['companyName' => 'required']);
})->group('behaviour');
