<?php

namespace Database\Factories;

use App\Enums\BusinessPartnerType;
use App\Models\BusinessPartner;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessPartner>
 */
class BusinessPartnerFactory extends Factory
{
    protected $model = BusinessPartner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'type' => fake()->randomElement(BusinessPartnerType::cases())->value,
            'tax_no' => fake()->optional()->numerify('##########'),
            'contact_person' => fake()->optional()->name(),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->companyEmail(),
            'address' => fake()->optional()->streetAddress(),
            'city' => fake()->optional()->city(),
            'country' => fake()->optional()->country(),
            'iban' => fake()->optional()->iban(),
            'payment_terms_days' => fake()->optional()->randomElement([0, 15, 30, 45, 60, 90]),
            'is_active' => true,
            'notes' => null,
            'meta' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function carrier(): static
    {
        return $this->state(['type' => BusinessPartnerType::Carrier->value]);
    }

    public function supplier(): static
    {
        return $this->state(['type' => BusinessPartnerType::Supplier->value]);
    }
}
