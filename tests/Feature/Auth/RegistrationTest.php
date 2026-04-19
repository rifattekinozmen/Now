<?php

use App\Authorization\LogisticsPermission;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Fortify\Features;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    /** @var TestCase $this */
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    /** @var TestCase $this */
    // Ensure this is not the very first user (first user gets super-admin, not tenant-user)
    User::factory()->create();

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->tenant_id)->not->toBeNull()
        ->and($user->hasRole(RolesAndPermissionsSeeder::ROLE_TENANT_USER))->toBeTrue()
        ->and($user->can(LogisticsPermission::ADMIN))->toBeTrue();

    $this->assertDatabaseHas('tenants', [
        'id' => $user->tenant_id,
    ]);
});
