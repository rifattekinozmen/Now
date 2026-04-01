<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\AccountTransaction;
use App\Models\CurrentAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountTransaction>
 */
class AccountTransactionFactory extends Factory
{
    protected $model = AccountTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'current_account_id' => CurrentAccount::factory(),
            'transaction_type' => fake()->randomElement(TransactionType::cases()),
            'amount' => fake()->randomFloat(2, 100, 50000),
            'currency_code' => 'TRY',
            'exchange_rate' => 1,
            'transaction_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'due_date' => fake()->optional(0.6)->dateTimeBetween('now', '+90 days')?->format('Y-m-d'),
            'reference_no' => fake()->optional()->bothify('REF-####-??'),
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function debit(): static
    {
        return $this->state(['transaction_type' => TransactionType::Debit]);
    }

    public function payment(): static
    {
        return $this->state(['transaction_type' => TransactionType::Payment]);
    }
}
