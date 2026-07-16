<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('CUS-####'), 'name' => fake()->company(), 'type' => 'private',
            'tin' => null, 'address' => fake()->address(), 'contact_person' => fake()->name(),
            'email' => fake()->companyEmail(), 'phone' => fake()->phoneNumber(), 'payment_terms' => 30,
            'status' => 'active', 'created_by' => User::factory(), 'updated_by' => User::factory(),
        ];
    }
}
