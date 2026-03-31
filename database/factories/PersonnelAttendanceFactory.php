<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Employee;
use App\Models\PersonnelAttendance;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonnelAttendance>
 */
class PersonnelAttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'check_in' => '09:00:00',
            'check_out' => '18:00:00',
            'status' => AttendanceStatus::Present->value,
            'note' => null,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceStatus::Absent->value,
            'check_in' => null,
            'check_out' => null,
        ]);
    }

    public function approved(User $approver): static
    {
        return $this->state(fn (array $attributes) => [
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
    }
}
