<?php

use App\Models\User;
use Tests\TestCase;

test('guests are redirected to the login page', function () {
    /** @var TestCase $this */
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee(__('Operations overview'), false);
    $response->assertSee(__('Shipment status distribution'), false);
    $response->assertSee(__('Refresh TCMB rates'), false);

    $html = $response->getContent();
    expect(substr_count($html, '<body'))->toBe(1)
        ->and(substr_count($html, '</body>'))->toBe(1);
});

test('logistics viewer does not see tcmb refresh on dashboard', function () {
    /** @var TestCase $this */
    $user = User::factory()->logisticsViewer()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(__('Refresh TCMB rates'), false);
});
