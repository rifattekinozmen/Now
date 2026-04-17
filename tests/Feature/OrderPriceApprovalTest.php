<?php

use App\Authorization\LogisticsPermission;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => LogisticsPermission::ADMIN, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => LogisticsPermission::VIEW, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => LogisticsPermission::ORDERS_WRITE, 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────
// Saving — minimum freight threshold
// ─────────────────────────────────────────────

it('creates order as draft when no minimum freight is set', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo([LogisticsPermission::ADMIN, LogisticsPermission::ORDERS_WRITE]);

    Livewire::actingAs($admin)
        ->test('pages::admin.orders-index')
        ->set('customer_id', $customer->id)
        ->set('freight_amount', '5000')
        ->call('saveOrder');

    $this->assertDatabaseHas('orders', [
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Draft->value,
    ]);
})->group('behaviour');

it('sets order status to pending_price_approval when freight is below minimum', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo([LogisticsPermission::ADMIN, LogisticsPermission::ORDERS_WRITE]);

    TenantSetting::set($tenant->id, 'minimum_freight_amount', '10000');

    Livewire::actingAs($admin)
        ->test('pages::admin.orders-index')
        ->set('customer_id', $customer->id)
        ->set('freight_amount', '5000')
        ->call('saveOrder');

    $this->assertDatabaseHas('orders', [
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::PendingPriceApproval->value,
    ]);
})->group('behaviour');

it('creates order as draft when freight meets or exceeds minimum', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo([LogisticsPermission::ADMIN, LogisticsPermission::ORDERS_WRITE]);

    TenantSetting::set($tenant->id, 'minimum_freight_amount', '5000');

    Livewire::actingAs($admin)
        ->test('pages::admin.orders-index')
        ->set('customer_id', $customer->id)
        ->set('freight_amount', '5000')
        ->call('saveOrder');

    $this->assertDatabaseHas('orders', [
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Draft->value,
    ]);
})->group('behaviour');

// ─────────────────────────────────────────────
// Approval — order-show
// ─────────────────────────────────────────────

it('admin can approve price on a pending_price_approval order', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo(LogisticsPermission::ADMIN);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::PendingPriceApproval,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.order-show', ['order' => $order])
        ->call('approvePrice');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Draft)
        ->and($order->price_approved_by)->toBe($admin->id)
        ->and($order->price_approved_at)->not->toBeNull();
})->group('behaviour');

it('non-admin cannot approve price', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->logisticsViewer()->create(['tenant_id' => $tenant->id]);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::PendingPriceApproval,
    ]);

    Livewire::actingAs($viewer)
        ->test('pages::admin.order-show', ['order' => $order])
        ->call('approvePrice');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::PendingPriceApproval);
})->group('behaviour');
