<?php

use App\Models\User;

test('invalid locale switch returns 404', function () {
    $this->get(route('locale.switch', ['locale' => 'xx']))->assertNotFound();
});

test('authenticated user can switch locale and session is stored', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->get(route('locale.switch', ['locale' => 'en']))
        ->assertRedirect();

    expect(session('locale'))->toBe('en');
});

test('locale from session is applied on next request', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('dashboard'))
        ->assertSuccessful();

    expect(app()->getLocale())->toBe('en');
});
