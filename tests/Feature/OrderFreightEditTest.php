<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Livewire\Livewire;

// ─────────────────────────────────────────────
// BEHAVIOUR — order-show freight editing
// ─────────────────────────────────────────────

it('admin can update freight fields from order show', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'status' => OrderStatus::Draft,
        'freight_amount' => null,
        'distance_km' => null,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.order-show', ['order' => $order])
        ->set('editFreightAmount', '5000')
        ->set('editCurrencyCode', 'EUR')
        ->set('editDistanceKm', '350')
        ->set('editTonnage', '26')
        ->set('editLoadingSite', 'Adana Fabrika')
        ->set('editUnloadingSite', 'İstanbul Depo')
        ->call('saveFreight')
        ->assertHasNoErrors();

    $order->refresh();
    expect((float) $order->freight_amount)->toBe(5000.0);
    expect($order->currency_code)->toBe('EUR');
    expect((float) $order->distance_km)->toBe(350.0);
    expect($order->loading_site)->toBe('Adana Fabrika');
    expect($order->unloading_site)->toBe('İstanbul Depo');
})->group('behaviour');

it('non-admin cannot save freight changes', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->logisticsViewer()->create(['tenant_id' => $tenant->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'status' => OrderStatus::Draft,
        'freight_amount' => 1000,
    ]);

    Livewire::actingAs($viewer)
        ->test('pages::admin.order-show', ['order' => $order])
        ->set('editFreightAmount', '9999')
        ->call('saveFreight');

    $order->refresh();
    expect((float) $order->freight_amount)->toBe(1000.0);
})->group('behaviour');

it('locked order cannot be freight-edited', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'status' => OrderStatus::Confirmed,
        'freight_amount' => 2000,
        'locked_at' => now(),
        'locked_by' => $admin->id,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.order-show', ['order' => $order])
        ->set('editFreightAmount', '8888')
        ->call('saveFreight');

    $order->refresh();
    expect((float) $order->freight_amount)->toBe(2000.0);
})->group('behaviour');

it('freight below minimum triggers pending price approval status', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    TenantSetting::set($tenant->id, 'minimum_freight_amount', '10000');

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'status' => OrderStatus::Draft,
        'freight_amount' => 5000,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.order-show', ['order' => $order])
        ->set('editFreightAmount', '500')
        ->call('saveFreight')
        ->assertHasNoErrors();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::PendingPriceApproval);
})->group('behaviour');
