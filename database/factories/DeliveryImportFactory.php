<?php

namespace Database\Factories;

use App\Enums\DeliveryImportStatus;
use App\Models\DeliveryImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryImport>
 */
class DeliveryImportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rowCount = $this->faker->numberBetween(10, 200);
        $matched = $this->faker->numberBetween(0, $rowCount);

        return [
            'reference_no' => 'IMP-'.strtoupper($this->faker->bothify('####??')),
            'import_date' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'source' => $this->faker->randomElement(['excel', 'csv']),
            'status' => $this->faker->randomElement(DeliveryImportStatus::cases())->value,
            'row_count' => $rowCount,
            'matched_count' => $matched,
            'unmatched_count' => $rowCount - $matched,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function processed(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => DeliveryImportStatus::Processed->value,
            'matched_count' => $attrs['row_count'],
            'unmatched_count' => 0,
        ]);
    }

    public function withErrors(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryImportStatus::Error->value,
        ]);
    }
}
