<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'sku' => fake()->unique()->bothify('SKU-####'),
            'name' => fake()->words(3, true),
            'unit' => fake()->randomElement(['kg', 't', 'pallet', 'unit']),
            'meta' => null,
        ];
    }
}
