<?php

use App\Models\AppNotification;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.view', 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────
// ROUTE — page access
// ─────────────────────────────────────────────

it('user can access their own notification show page', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');

    $notification = AppNotification::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'type' => 'info',
        'title' => 'Test notification',
        'body' => 'Test body',
    ]);

    $this->actingAs($user)
        ->get(route('admin.notifications.show', $notification))
        ->assertSuccessful();
})->group('routes');

it('user cannot access another user notification', function (): void {
    $tenant = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenant->id]);
    $userA->givePermissionTo('logistics.admin');
    $userB = User::factory()->create(['tenant_id' => $tenant->id]);

    $notification = AppNotification::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $userB->id,
        'type' => 'info',
        'title' => 'Other user notification',
    ]);

    $this->actingAs($userA)
        ->get(route('admin.notifications.show', $notification))
        ->assertForbidden();
})->group('routes');

it('unauthenticated user is redirected from notification show', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $notification = AppNotification::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'type' => 'info',
        'title' => 'Test',
    ]);

    $this->get(route('admin.notifications.show', $notification))
        ->assertRedirect();
})->group('routes');

// ─────────────────────────────────────────────
// BEHAVIOUR — auto mark as read
// ─────────────────────────────────────────────

it('viewing notification marks it as read', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');

    $notification = AppNotification::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'type' => 'warning',
        'title' => 'Unread notification',
        'is_read' => false,
    ]);

    expect($notification->is_read)->toBeFalse();

    $this->actingAs($user)
        ->get(route('admin.notifications.show', $notification))
        ->assertSuccessful();

    expect($notification->fresh()->is_read)->toBeTrue();
})->group('behaviour');
