<?php

use App\Models\FuelPrice;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    RolesAndPermissionsSeeder::ensureDefaults();
});

test('guest cannot access fuel prices index', function () {
    $this->get(route('admin.fuel-prices.index'))->assertRedirect(route('login'));
});

test('logistics admin can access fuel prices index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('admin.fuel-prices.index'))->assertSuccessful();
});

test('logistics admin can create fuel price', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::admin.fuel-prices-index')
        ->call('startCreate')
        ->set('fuel_type', 'diesel')
        ->set('price', '45.5000')
        ->set('currency', 'TRY')
        ->set('recorded_at', '2026-03-30')
        ->call('save')
        ->assertHasNoErrors();

    expect(FuelPrice::query()->where('fuel_type', 'diesel')->count())->toBe(1);
});

test('logistics admin can edit fuel price', function () {
    $user = User::factory()->create();
    $row = FuelPrice::factory()->create(['tenant_id' => $user->tenant_id, 'fuel_type' => 'diesel', 'price' => '40.0000']);
    $this->actingAs($user);

    Livewire::test('pages::admin.fuel-prices-index')
        ->call('startEdit', $row->id)
        ->set('price', '42.5000')
        ->call('save')
        ->assertHasNoErrors();

    expect($row->fresh()?->price)->toBe('42.5000');
});

test('logistics admin can delete fuel price', function () {
    $user = User::factory()->create();
    $row = FuelPrice::factory()->create(['tenant_id' => $user->tenant_id]);
    $this->actingAs($user);

    Livewire::test('pages::admin.fuel-prices-index')
        ->call('delete', $row->id);

    expect(FuelPrice::query()->whereKey($row->id)->exists())->toBeFalse();
});

test('fuel prices from other tenants are not visible', function () {
    $userA = User::factory()->create();
    $tenantB = Tenant::factory()->create();
    FuelPrice::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA);

    expect(FuelPrice::query()->count())->toBe(0);
});

test('fuel price validation rejects invalid fuel type', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::admin.fuel-prices-index')
        ->call('startCreate')
        ->set('fuel_type', 'kerosene')
        ->set('price', '45.0000')
        ->set('recorded_at', '2026-03-30')
        ->call('save')
        ->assertHasErrors(['fuel_type']);
});
