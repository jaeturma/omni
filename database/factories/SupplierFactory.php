<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supplier> */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('SUP-####'), 'name' => fake()->company(), 'tin' => null,
            'address' => fake()->address(), 'contact_person' => fake()->name(), 'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(), 'payment_terms' => 30, 'status' => 'active',
            'created_by' => User::factory(), 'updated_by' => User::factory(),
        ];
    }
}
