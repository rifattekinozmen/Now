<?php

namespace Database\Factories;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Leave>
 */
class LeaveFactory extends Factory
{
    protected $model = Leave::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-3 months', '+1 month');
        $days  = $this->faker->numberBetween(1, 14);
        $end   = (clone $start)->modify("+{$days} days");

        return [
            'tenant_id'   => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'type'        => $this->faker->randomElement(LeaveType::cases())->value,
            'status'      => LeaveStatus::Pending->value,
            'start_date'  => $start->format('Y-m-d'),
            'end_date'    => $end->format('Y-m-d'),
            'days_count'  => $days,
            'reason'      => $this->faker->optional()->sentence(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attr) => [
            'status'      => LeaveStatus::Approved->value,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attr) => [
            'status'           => LeaveStatus::Rejected->value,
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }

    public function annual(): static
    {
        return $this->state(fn (array $attr) => [
            'type' => LeaveType::Annual->value,
        ]);
    }
}
