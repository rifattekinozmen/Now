<?php

namespace Database\Factories;

use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Models\MaintenanceSchedule;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceSchedule>
 */
class MaintenanceScheduleFactory extends Factory
{
    protected $model = MaintenanceSchedule::class;

    public function definition(): array
    {
        return [
            'tenant_id'        => Tenant::factory(),
            'vehicle_id'       => Vehicle::factory(),
            'title'            => $this->faker->randomElement([
                'Yağ Değişimi', 'Fren Kontrolü', 'Lastik Rotasyonu',
                'Filtre Bakımı', 'Motor Kontrolü', 'Periyodik Muayene',
            ]),
            'type'             => $this->faker->randomElement(MaintenanceType::cases())->value,
            'status'           => MaintenanceStatus::Scheduled->value,
            'scheduled_date'   => $this->faker->dateTimeBetween('-1 month', '+2 months')->format('Y-m-d'),
            'km_at_service'    => $this->faker->optional()->numberBetween(10000, 500000),
            'next_km'          => $this->faker->optional()->numberBetween(10000, 500000),
            'cost'             => $this->faker->optional()->randomFloat(2, 500, 15000),
            'service_provider' => $this->faker->optional()->company(),
            'notes'            => $this->faker->optional()->sentence(),
        ];
    }

    public function done(): static
    {
        return $this->state(fn (array $attr) => [
            'status'         => MaintenanceStatus::Done->value,
            'completed_date' => now()->subDays(rand(1, 30))->format('Y-m-d'),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attr) => [
            'status'         => MaintenanceStatus::Scheduled->value,
            'scheduled_date' => now()->addDays(rand(1, 7))->format('Y-m-d'),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attr) => [
            'status'         => MaintenanceStatus::Scheduled->value,
            'scheduled_date' => now()->subDays(rand(1, 30))->format('Y-m-d'),
        ]);
    }
}
