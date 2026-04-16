<?php

namespace Database\Factories;

use App\Enums\BankTransactionType;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankTransaction>
 */
class BankTransactionFactory extends Factory
{
    protected $model = BankTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'bank_account_id' => BankAccount::factory(),
            'transaction_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'amount' => fake()->randomFloat(2, 100, 500000),
            'currency_code' => 'TRY',
            'transaction_type' => fake()->randomElement(BankTransactionType::cases())->value,
            'reference_no' => fake()->optional()->numerify('TXN-########'),
            'description' => fake()->optional()->sentence(),
            'is_reconciled' => false,
        ];
    }

    public function credit(): static
    {
        return $this->state(['transaction_type' => BankTransactionType::Credit->value]);
    }

    public function debit(): static
    {
        return $this->state(['transaction_type' => BankTransactionType::Debit->value]);
    }

    public function reconciled(): static
    {
        return $this->state(['is_reconciled' => true]);
    }
}
