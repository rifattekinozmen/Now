<?php

namespace Database\Factories;

use App\Enums\AdvanceStatus;
use App\Models\Advance;
use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Advance>
 */
class AdvanceFactory extends Factory
{
    protected $model = Advance::class;

    public function definition(): array
    {
        return [
            'tenant_id'      => Tenant::factory(),
            'employee_id'    => Employee::factory(),
            'amount'         => $this->faker->randomFloat(2, 500, 25000),
            'currency_code'  => 'TRY',
            'requested_at'   => $this->faker->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'repayment_date' => $this->faker->dateTimeBetween('+1 month', '+6 months')->format('Y-m-d'),
            'status'         => AdvanceStatus::Pending->value,
            'reason'         => $this->faker->optional()->sentence(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attr) => [
            'status'      => AdvanceStatus::Approved->value,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attr) => [
            'status'           => AdvanceStatus::Rejected->value,
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }

    public function repaid(): static
    {
        return $this->state(fn (array $attr) => [
            'status'      => AdvanceStatus::Repaid->value,
            'approved_at' => now()->subDays(30),
        ]);
    }
}
