<?php

use App\Authorization\LogisticsPermission;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => LogisticsPermission::ADMIN, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => LogisticsPermission::VIEW, 'guard_name' => 'web']);
});

it('admin can lock an unlocked order', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Delivered,
    ]);

    expect($order->isLocked())->toBeFalse();

    Livewire::actingAs($admin)
        ->test('pages::admin.order-show', ['order' => $order])
        ->call('lockOrder');

    $order->refresh();
    expect($order->isLocked())->toBeTrue()
        ->and($order->locked_by)->toBe($admin->id)
        ->and($order->locked_at)->not->toBeNull();
})->group('behaviour');

it('non-admin cannot lock an order', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->logisticsViewer()->create(['tenant_id' => $tenant->id]);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Delivered,
    ]);

    Livewire::actingAs($viewer)
        ->test('pages::admin.order-show', ['order' => $order])
        ->call('lockOrder');

    $order->refresh();
    expect($order->isLocked())->toBeFalse();
})->group('behaviour');

it('locking an already locked order is a no-op', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);

    $originalLockedAt = now()->subDay();
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Delivered,
        'locked_at' => $originalLockedAt,
        'locked_by' => $admin->id,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.order-show', ['order' => $order])
        ->call('lockOrder');

    $order->refresh();
    expect($order->locked_at->toDateString())->toBe($originalLockedAt->toDateString());
})->group('behaviour');

it('approvePrice is blocked on locked orders', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::PendingPriceApproval,
        'locked_at' => now(),
        'locked_by' => $admin->id,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.order-show', ['order' => $order])
        ->call('approvePrice');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::PendingPriceApproval);
})->group('behaviour');
