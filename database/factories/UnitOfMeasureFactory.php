<?php

namespace Database\Factories;

use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnitOfMeasure> */
class UnitOfMeasureFactory extends Factory
{
    public function definition(): array
    {
        $word = fake()->unique()->word();

        return [
            'code' => str($word)->upper()->limit(20, '')->toString(), 'name' => str($word)->headline()->toString(),
            'status' => 'active', 'created_by' => User::factory(), 'updated_by' => User::factory(),
        ];
    }
}
