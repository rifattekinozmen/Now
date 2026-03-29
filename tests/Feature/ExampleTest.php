<?php

use App\Models\User;
use Tests\TestCase;

test('home redirects guests to login', function () {
    /** @var TestCase $this */
    $this->get(route('home'))
        ->assertRedirect(route('login'));
});

test('home redirects authenticated users to dashboard', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
