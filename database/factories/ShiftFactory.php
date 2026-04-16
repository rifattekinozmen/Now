<?php

namespace Database\Factories;

use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'shift_date' => fake()->dateTimeBetween('-30 days', '+30 days')->format('Y-m-d'),
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'shift_type' => ShiftType::Regular,
            'status' => ShiftStatus::Planned,
            'notes' => null,
            'meta' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => ShiftStatus::Confirmed]);
    }

    public function absent(): static
    {
        return $this->state(['status' => ShiftStatus::Absent]);
    }

    public function night(): static
    {
        return $this->state([
            'shift_type' => ShiftType::Night,
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
        ]);
    }
}
