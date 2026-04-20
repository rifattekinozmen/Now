<?php

namespace Database\Factories;

use App\Models\TaxOffice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxOffice>
 */
class TaxOfficeFactory extends Factory
{
    protected $model = TaxOffice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $cities = ['İstanbul', 'Ankara', 'İzmir', 'Bursa', 'Antalya', 'Konya', 'Adana', 'Gaziantep'];

        return [
            'code' => fake()->unique()->numerify('TAX-####'),
            'name' => fake()->company().' Vergi Dairesi',
            'city' => fake()->randomElement($cities),
            'district' => fake()->city(),
            'is_active' => true,
        ];
    }
}
