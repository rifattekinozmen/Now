<?php

use App\Models\FuelIntake;
use App\Models\User;
use App\Models\Vehicle;
use Livewire\Livewire;

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
