<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    private static array $banks = [
        'Ziraat Bankası', 'Garanti BBVA', 'İş Bankası', 'Yapı Kredi',
        'Halkbank', 'Vakıfbank', 'Akbank', 'QNB Finansbank', 'Denizbank',
    ];

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->words(2, true).' Account',
            'bank_name' => $this->faker->randomElement(self::$banks),
            'account_number' => $this->faker->optional()->numerify('##########'),
            'iban' => $this->faker->optional()->bothify('TR##????????????????????##########'),
            'currency_code' => $this->faker->randomElement(['TRY', 'USD', 'EUR']),
            'opening_balance' => $this->faker->randomFloat(2, 0, 100000),
            'opened_at' => $this->faker->optional()->dateTimeBetween('-5 years', '-1 month')?->format('Y-m-d'),
            'is_active' => true,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attr) => ['is_active' => false]);
    }

    public function try(): static
    {
        return $this->state(fn (array $attr) => ['currency_code' => 'TRY']);
    }
}
