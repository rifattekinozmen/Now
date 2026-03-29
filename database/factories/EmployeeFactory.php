<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'national_id' => null,
            'blood_group' => fake()->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', '0+', '0-']),
            'is_driver' => false,
            'license_class' => null,
            'license_valid_until' => null,
            'src_valid_until' => null,
            'psychotechnical_valid_until' => null,
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'meta' => null,
        ];
    }

    public function driver(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_driver' => true,
            'license_class' => 'E',
            'license_valid_until' => now()->addYear(),
            'src_valid_until' => now()->addMonths(6),
            'psychotechnical_valid_until' => now()->addMonths(3),
        ]);
    }
}
