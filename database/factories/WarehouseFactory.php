<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Warehouse> */
class WarehouseFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('WH-####'), 'name' => fake()->unique()->company().' Warehouse', 'address' => fake()->address(), 'status' => 'active', 'created_by' => User::factory(), 'updated_by' => User::factory()];
    }
}
