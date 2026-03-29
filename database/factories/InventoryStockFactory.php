<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryStock>
 */
class InventoryStockFactory extends Factory
{
    protected $model = InventoryStock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'warehouse_id' => Warehouse::factory()->create(['tenant_id' => $tenant->id])->id,
            'inventory_item_id' => InventoryItem::factory()->create(['tenant_id' => $tenant->id])->id,
            'quantity' => fake()->randomFloat(4, 0, 1000),
            'meta' => null,
        ];
    }
}
