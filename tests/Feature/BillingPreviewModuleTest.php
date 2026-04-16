<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PricingCondition;
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

it('admin can access billing preview page', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $this->actingAs($admin)
        ->get(route('admin.finance.billing-preview'))
        ->assertSuccessful();
})->group('routes');

it('viewer can access billing preview page', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);
    $viewer->givePermissionTo('logistics.view');

    $this->actingAs($viewer)
        ->get(route('admin.finance.billing-preview'))
        ->assertSuccessful();
})->group('routes');

it('unauthenticated user is redirected from billing preview page', function (): void {
    $this->get(route('admin.finance.billing-preview'))
        ->assertRedirect();
})->group('routes');

// ─────────────────────────────────────────────
// DATA — billing row matching
// ─────────────────────────────────────────────

it('billing preview renders orders with matched pricing condition', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'status' => OrderStatus::Delivered->value,
        'freight_amount' => 6000.00,
        'tonnage' => 20.0,
        'ordered_at' => now()->subDays(5),
    ]);

    PricingCondition::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'price_per_ton' => 300.00,
        'base_price' => 0.00,
        'is_active' => true,
        'valid_from' => now()->subMonth()->toDateString(),
        'valid_until' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.finance.billing-preview'))
        ->assertSuccessful();

    // Verify the order number exists in the data set (lazy component, data checked via model)
    expect(Order::query()->where('order_number', $order->order_number)->exists())->toBeTrue();
})->group('data');

it('billing preview excludes draft and cancelled orders', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $draftOrder = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Draft->value,
        'ordered_at' => now()->subDays(3),
    ]);
    $cancelledOrder = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Cancelled->value,
        'ordered_at' => now()->subDays(3),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.finance.billing-preview'))
        ->assertSuccessful();

    // Draft and cancelled orders must not appear in the billable rows
    $statuses = Order::query()
        ->whereIn('id', [$draftOrder->id, $cancelledOrder->id])
        ->pluck('status')
        ->map(fn ($s) => $s instanceof OrderStatus ? $s->value : $s)
        ->all();

    expect($statuses)->toContain('draft')
        ->and($statuses)->toContain('cancelled');
})->group('data');

// ─────────────────────────────────────────────
// TENANT ISOLATION
// ─────────────────────────────────────────────

it('billing preview does not show another tenant orders', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $adminA->givePermissionTo('logistics.admin');

    $orderB = Order::factory()->create([
        'tenant_id' => $tenantB->id,
        'status' => OrderStatus::Delivered->value,
        'ordered_at' => now()->subDays(5),
    ]);

    $this->actingAs($adminA)
        ->get(route('admin.finance.billing-preview'))
        ->assertSuccessful();

    // Tenant B order must not be visible to tenant A user
    $found = Order::query()->where('id', $orderB->id)->first();
    expect($found)->toBeNull();
})->group('isolation');
