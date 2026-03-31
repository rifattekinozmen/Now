<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegister>
 */
class CashRegisterFactory extends Factory
{
    protected $model = CashRegister::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id'       => Tenant::factory(),
            'name'            => fake()->words(2, true).' Kasası',
            'code'            => strtoupper(fake()->lexify('KAS-???')),
            'currency_code'   => fake()->randomElement(['TRY', 'USD', 'EUR']),
            'current_balance' => fake()->randomFloat(2, 0, 100000),
            'is_active'       => true,
            'description'     => fake()->optional()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
