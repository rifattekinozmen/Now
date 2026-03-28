<?php

use App\Models\User;
use Tests\TestCase;

uses(TestCase::class);

test('guests cannot access admin logistics routes', function () {
    $this->get(route('admin.customers.index'))->assertRedirect(route('login'));
    $this->get(route('admin.vehicles.index'))->assertRedirect(route('login'));
    $this->get(route('admin.orders.index'))->assertRedirect(route('login'));
    $this->get(route('admin.shipments.index'))->assertRedirect(route('login'));
    $this->get(route('admin.delivery-numbers.index'))->assertRedirect(route('login'));
});

test('authenticated users can access admin logistics routes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('admin.customers.index'))->assertSuccessful();
    $this->get(route('admin.vehicles.index'))->assertSuccessful();
    $this->get(route('admin.orders.index'))->assertSuccessful();
    $this->get(route('admin.shipments.index'))->assertSuccessful();
    $this->get(route('admin.delivery-numbers.index'))->assertSuccessful();
});
