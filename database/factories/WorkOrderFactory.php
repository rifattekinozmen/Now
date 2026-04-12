<?php

namespace Database\Factories;

use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'vehicle_id' => Vehicle::factory(),
            'employee_id' => null,
            'title' => $this->faker->randomElement([
                'Yağ Değişimi', 'Fren Bakımı', 'Lastik Kontrolü',
                'Elektrik Arızası Giderme', 'Motor Revizyonu', 'Periyodik Bakım',
                'Hava Filtresi Değişimi', 'Akü Değişimi', 'Cam Silecek Bakımı',
            ]),
            'description' => $this->faker->optional()->paragraph(),
            'type' => $this->faker->randomElement(WorkOrderType::cases())->value,
            'status' => WorkOrderStatus::Pending->value,
            'scheduled_at' => $this->faker->dateTimeBetween('-1 month', '+2 months')->format('Y-m-d'),
            'completed_at' => null,
            'cost' => $this->faker->optional()->randomFloat(2, 200, 20000),
            'service_provider' => $this->faker->optional()->company(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attr) => [
            'status' => WorkOrderStatus::Completed->value,
            'completed_at' => now()->subDays(rand(1, 30))->format('Y-m-d'),
            'cost' => $this->faker->randomFloat(2, 500, 15000),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attr) => [
            'status' => WorkOrderStatus::InProgress->value,
        ]);
    }
}
