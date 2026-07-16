<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Brand> */
class BrandFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return ['code' => fake()->unique()->bothify('BR-####'), 'name' => $name, 'status' => 'active', 'created_by' => User::factory(), 'updated_by' => User::factory()];
    }
}
