<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\ProductService;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductService> */
class ProductServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sku' => fake()->unique()->bothify('SKU-#####'), 'barcode' => null, 'name' => fake()->words(3, true),
            'description' => fake()->sentence(), 'type' => 'product', 'category_id' => Category::factory(),
            'unit_of_measure_id' => UnitOfMeasure::factory(), 'default_cost' => '100.0000',
            'selling_price' => '125.0000', 'reorder_level' => '5.0000', 'is_inventory' => true,
            'status' => 'active', 'created_by' => User::factory(), 'updated_by' => User::factory(),
        ];
    }
}
