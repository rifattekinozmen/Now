<?php

namespace Database\Factories;

use App\Models\ChartAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChartAccount>
 */
class ChartAccountFactory extends Factory
{
    protected $model = ChartAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => fake()->unique()->numerify('ACC####'),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['asset', 'liability', 'equity', 'revenue', 'expense']),
        ];
    }
}
