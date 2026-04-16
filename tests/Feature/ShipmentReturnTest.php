<?php

use App\Models\Order;
use App\Models\Shipment;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.view', 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────
// MODEL — return fields exist and default correctly
// ─────────────────────────────────────────────

it('shipment has is_return false by default', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');
    $this->actingAs($user);

    $order = Order::factory()->create(['tenant_id' => $tenant->id]);
    $shipment = Shipment::factory()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    expect($shipment->is_return)->toBeFalse()
        ->and($shipment->return_reason)->toBeNull()
        ->and($shipment->return_photo_path)->toBeNull();
});

it('can mark a shipment as return with reason', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');
    $this->actingAs($user);

    $order = Order::factory()->create(['tenant_id' => $tenant->id]);
    $shipment = Shipment::factory()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $shipment->update([
        'is_return' => true,
        'return_reason' => 'Product damaged during transport',
    ]);

    $shipment->refresh();

    expect($shipment->is_return)->toBeTrue()
        ->and($shipment->return_reason)->toBe('Product damaged during transport');
});

// ─────────────────────────────────────────────
// ROUTE — show page accessible
// ─────────────────────────────────────────────

it('admin can access shipment show page', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $order = Order::factory()->create(['tenant_id' => $tenant->id]);
    $shipment = Shipment::factory()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.shipments.show', $shipment))
        ->assertSuccessful();
})->group('routes');

// ─────────────────────────────────────────────
// TENANT ISOLATION — return fields
// ─────────────────────────────────────────────

it('cannot update return info on another tenant\'s shipment', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    $orderB = Order::factory()->create(['tenant_id' => $tenantB->id]);
    $shipmentB = Shipment::factory()->create([
        'tenant_id' => $tenantB->id,
        'order_id' => $orderB->id,
    ]);

    // Acting as tenant A user
    $this->actingAs($userA);

    // Try to directly update — BelongsToTenant scope should prevent finding it
    $found = Shipment::query()->where('id', $shipmentB->id)->first();
    expect($found)->toBeNull();
})->group('isolation');
