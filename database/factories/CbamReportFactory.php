<?php

namespace Database\Factories;

use App\Models\CbamReport;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CbamReport>
 */
class CbamReportFactory extends Factory
{
    protected $model = CbamReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $distanceKm = fake()->randomFloat(2, 100, 3000);
        $fuelL = $distanceKm * fake()->randomFloat(3, 0.28, 0.40); // 28-40 L/100km range → L/km

        return [
            'tenant_id' => Tenant::factory(),
            'shipment_id' => null,
            'co2_kg' => round($fuelL * 2.64, 3), // ~2.64 kg CO2 per litre diesel
            'distance_km' => $distanceKm,
            'fuel_consumption_l' => $fuelL,
            'tonnage' => fake()->randomFloat(3, 1, 25),
            'vehicle_type' => 'truck',
            'report_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'status' => fake()->randomElement(['draft', 'submitted', 'accepted']),
        ];
    }

    public function submitted(): static
    {
        return $this->state(['status' => 'submitted']);
    }

    public function accepted(): static
    {
        return $this->state(['status' => 'accepted']);
    }
}
