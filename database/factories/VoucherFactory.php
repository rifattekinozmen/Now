<?php

namespace Database\Factories;

use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\CashRegister;
use App\Models\Tenant;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id'        => Tenant::factory(),
            'cash_register_id' => CashRegister::factory(),
            'order_id'         => null,
            'approved_by'      => null,
            'type'             => fake()->randomElement(VoucherType::cases())->value,
            'status'           => VoucherStatus::Pending->value,
            'amount'           => fake()->randomFloat(2, 50, 50000),
            'currency_code'    => 'TRY',
            'voucher_date'     => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'reference_no'     => fake()->optional()->numerify('REF-####'),
            'description'      => fake()->sentence(),
        ];
    }

    public function expense(): static
    {
        return $this->state(['type' => VoucherType::Expense->value]);
    }

    public function income(): static
    {
        return $this->state(['type' => VoucherType::Income->value]);
    }

    public function pending(): static
    {
        return $this->state(['status' => VoucherStatus::Pending->value, 'approved_by' => null, 'approved_at' => null]);
    }

    public function approved(): static
    {
        return $this->state([
            'status'      => VoucherStatus::Approved->value,
            'approved_at' => now(),
        ]);
    }
}
