<?php

use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

test('warehouse rows are isolated per tenant on query', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

    Warehouse::factory()->create([
        'tenant_id' => $tenantA->id,
        'code' => 'WH-ONLY-A',
        'name' => 'Secret A Warehouse',
    ]);

    $this->actingAs($userB);

    expect(Warehouse::query()->pluck('code')->all())->toBe([]);
});

test('admin warehouse page shows only own tenant warehouses', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

    Warehouse::factory()->create([
        'tenant_id' => $tenantA->id,
        'code' => 'WH-A1',
        'name' => 'Tenant A Warehouse',
    ]);

    $this->actingAs($userB)
        ->get(route('admin.warehouse.index'))
        ->assertSuccessful()
        ->assertDontSee('WH-A1');

    $this->actingAs($userA)
        ->get(route('admin.warehouse.index'))
        ->assertSuccessful()
        ->assertSee('WH-A1');
});

test('livewire can create a warehouse', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::admin.warehouse-index')
        ->call('startCreateWarehouse')
        ->set('warehouse_code', 'MAIN-01')
        ->set('warehouse_name', 'Main depot')
        ->set('warehouse_address', 'Industrial zone')
        ->call('saveWarehouse')
        ->assertHasNoErrors();

    $wh = Warehouse::query()->where('code', 'MAIN-01')->firstOrFail();
    expect((int) $wh->tenant_id)->toBe((int) $user->tenant_id);
});

test('inventory stock row respects tenant and unique pair', function () {
    $user = User::factory()->create();
    $wh = Warehouse::factory()->create(['tenant_id' => $user->tenant_id, 'code' => 'W1']);
    $item = InventoryItem::factory()->create(['tenant_id' => $user->tenant_id, 'sku' => 'SKU-1']);

    $this->actingAs($user);

    InventoryStock::query()->create([
        'warehouse_id' => $wh->id,
        'inventory_item_id' => $item->id,
        'quantity' => '99.0000',
    ]);

    $stock = InventoryStock::query()
        ->where('warehouse_id', $wh->id)
        ->where('inventory_item_id', $item->id)
        ->firstOrFail();

    expect((float) $stock->quantity)->toBe(99.0)
        ->and((int) $stock->tenant_id)->toBe((int) $user->tenant_id);
});
