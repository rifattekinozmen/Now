<?php

use App\Models\FuelIntake;
use App\Models\User;
use App\Models\Vehicle;
use Livewire\Livewire;

test('vehicles from other tenants are not visible for fuel intake save path', function () {
    $userA = User::factory()->create();
    $vehicleB = Vehicle::factory()->create();

    expect($vehicleB->tenant_id)->not->toBe($userA->tenant_id);

    $this->actingAs($userA);

    expect(Vehicle::query()->whereKey($vehicleB->id)->first())->toBeNull();
});

test('guest cannot access fuel intakes', function () {
    $this->get(route('admin.fuel-intakes.index'))->assertRedirect(route('login'));
});

test('logistics admin can create fuel intake', function () {
    $user = User::factory()->create();
    $vehicle = Vehicle::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user);

    Livewire::test('pages::admin.fuel-intakes-index')
        ->call('startCreate')
        ->set('vehicle_id', (string) $vehicle->id)
        ->set('liters', '120.5')
        ->set('odometer_km', '100000')
        ->call('save')
        ->assertHasNoErrors();

    expect(FuelIntake::query()->where('vehicle_id', $vehicle->id)->count())->toBe(1);
});
