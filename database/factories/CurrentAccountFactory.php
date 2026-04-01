<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\CurrentAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CurrentAccount>
 */
class CurrentAccountFactory extends Factory
{
    protected $model = CurrentAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'account_type' => AccountType::Customer,
            'code' => strtoupper(fake()->lexify('CAR-???')),
            'name' => fake()->company(),
            'balance' => fake()->randomFloat(2, -50000, 200000),
            'currency_code' => 'TRY',
            'credit_limit' => fake()->randomFloat(2, 0, 500000),
            'payment_term_days' => fake()->randomElement([0, 30, 45, 60, 90]),
            'is_active' => true,
        ];
    }

    public function forEmployee(): static
    {
        return $this->state(['account_type' => AccountType::Employee]);
    }

    public function forVehicle(): static
    {
        return $this->state(['account_type' => AccountType::Vehicle]);
    }

    public function forSupplier(): static
    {
        return $this->state(['account_type' => AccountType::Supplier]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
