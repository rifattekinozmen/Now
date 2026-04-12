<?php

use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;

it('unauthenticated user is redirected from inventory', function (): void {
    $this->get(route('admin.inventory.index'))
        ->assertRedirect();
});

it('authenticated user can access inventory index', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('admin.inventory.index'))
        ->assertSuccessful();
});

it('cannot read another tenant\'s inventory items', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

    InventoryItem::factory()->create(['tenant_id' => $tenantA->id]);
    $itemB = InventoryItem::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA);

    $records = InventoryItem::query()->get();
    expect($records->pluck('id'))->not->toContain($itemB->id);
});

it('cannot read another tenant\'s inventory stock', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

    InventoryStock::factory()->create(['tenant_id' => $tenantA->id]);
    $stockB = InventoryStock::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA);

    $records = InventoryStock::query()->get();
    expect($records->pluck('id'))->not->toContain($stockB->id);
});

it('inventory item has many stocks', function (): void {
    $tenant = Tenant::factory()->create();
    $item = InventoryItem::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse1 = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse2 = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    InventoryStock::factory()->create([
        'tenant_id' => $tenant->id,
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse1->id,
        'quantity' => 100,
    ]);
    InventoryStock::factory()->create([
        'tenant_id' => $tenant->id,
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse2->id,
        'quantity' => 50,
    ]);

    expect($item->inventoryStocks)->toHaveCount(2);
    expect((float) $item->inventoryStocks->sum('quantity'))->toBe(150.0);
});

it('inventory stock belongs to item and warehouse', function (): void {
    $tenant = Tenant::factory()->create();
    $item = InventoryItem::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    $stock = InventoryStock::factory()->create([
        'tenant_id' => $tenant->id,
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 75.5,
    ]);

    expect($stock->inventoryItem->id)->toBe($item->id);
    expect($stock->warehouse->id)->toBe($warehouse->id);
    expect((float) $stock->quantity)->toBe(75.5);
});

it('inventory stock quantity is cast to decimal', function (): void {
    $tenant = Tenant::factory()->create();
    $stock = InventoryStock::factory()->create([
        'tenant_id' => $tenant->id,
        'quantity' => 123.4567,
    ]);

    expect((float) $stock->fresh()->quantity)->toBe(123.4567);
});

it('inventory item sku is stored and retrieved correctly', function (): void {
    $tenant = Tenant::factory()->create();
    $item = InventoryItem::factory()->create(['tenant_id' => $tenant->id, 'sku' => 'SKU-TEST-001']);

    expect($item->fresh()->sku)->toBe('SKU-TEST-001');
});
