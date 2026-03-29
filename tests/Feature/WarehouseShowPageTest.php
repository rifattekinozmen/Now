<?php

use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;

test('user can open warehouse detail in their tenant', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user)
        ->get(route('admin.warehouse.show', $warehouse))
        ->assertSuccessful()
        ->assertSee($warehouse->code, escape: false);
});

test('user cannot open other tenant warehouse detail', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
    $warehouseA = Warehouse::factory()->create(['tenant_id' => $tenantA->id]);

    $this->actingAs($userB)
        ->get(route('admin.warehouse.show', $warehouseA))
        ->assertNotFound();
});

test('warehouse detail lists stock rows for that warehouse', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create(['tenant_id' => $user->tenant_id]);
    $item = InventoryItem::factory()->create(['tenant_id' => $user->tenant_id]);
    InventoryStock::factory()->create([
        'tenant_id' => $user->tenant_id,
        'warehouse_id' => $warehouse->id,
        'inventory_item_id' => $item->id,
        'quantity' => '12.5000',
    ]);

    $this->actingAs($user)
        ->get(route('admin.warehouse.show', $warehouse))
        ->assertSuccessful()
        ->assertSee($item->sku, escape: false);
});
