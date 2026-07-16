<?php

namespace Database\Factories;

use App\Models\BusinessProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessProfile>
 */
class BusinessProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registered_business_name' => fake()->company(),
            'trade_name' => fake()->company(),
            'proprietor_name' => fake()->name(),
            'tin' => '123-456-789',
            'branch_code' => '00000',
            'rdo_code' => '050',
            'registration_date' => '2026-05-01',
            'business_start_date' => '2026-05-01',
            'registered_address' => fake()->address(),
            'email' => fake()->companyEmail(),
            'default_currency' => 'PHP',
            'timezone' => 'Asia/Manila',
            'fiscal_year_start_month' => 1,
            'active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['active' => true]);
    }
}
