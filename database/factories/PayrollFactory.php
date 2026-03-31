<?php

namespace Database\Factories;

use App\Enums\PayrollStatus;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payroll>
 */
class PayrollFactory extends Factory
{
    protected $model = Payroll::class;

    public function definition(): array
    {
        $gross = $this->faker->randomFloat(2, 15000, 80000);
        $sgk   = round($gross * 0.14, 2); // Employee SGK share
        $tax   = round(($gross - $sgk) * 0.15, 2);
        $net   = round($gross - $sgk - $tax, 2);

        $periodStart = $this->faker->dateTimeBetween('-6 months', 'now');
        $periodStart->modify('first day of this month');
        $periodEnd = clone $periodStart;
        $periodEnd->modify('last day of this month');

        return [
            'tenant_id'     => Tenant::factory(),
            'employee_id'   => Employee::factory(),
            'period_start'  => $periodStart->format('Y-m-d'),
            'period_end'    => $periodEnd->format('Y-m-d'),
            'gross_salary'  => $gross,
            'deductions'    => [
                'sgk_employee' => $sgk,
                'income_tax'   => $tax,
            ],
            'net_salary'    => $net,
            'currency_code' => 'TRY',
            'status'        => PayrollStatus::Draft->value,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attr) => [
            'status'      => PayrollStatus::Approved->value,
            'approved_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attr) => [
            'status'      => PayrollStatus::Paid->value,
            'approved_at' => now()->subDays(5),
            'paid_at'     => now(),
        ]);
    }
}
