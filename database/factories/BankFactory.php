<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Bank> */ class BankFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('BK-####'), 'name' => fake()->unique()->company().' Bank', 'swift_code' => null, 'status' => 'active', 'created_by' => User::factory(), 'updated_by' => User::factory()];
    }
}
