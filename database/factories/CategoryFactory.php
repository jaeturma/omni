<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $word = fake()->unique()->words(2, true);

        return [
            'code' => fake()->unique()->bothify('CAT-####'), 'name' => str($word)->title()->toString(),
            'type' => 'product', 'parent_id' => null, 'status' => 'active',
            'created_by' => User::factory(), 'updated_by' => User::factory(),
        ];
    }
}
