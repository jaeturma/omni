<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentMethod> */ class PaymentMethodFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('PM-####'), 'name' => fake()->unique()->words(2, true), 'type' => 'cash', 'status' => 'active', 'created_by' => User::factory(), 'updated_by' => User::factory()];
    }
}
