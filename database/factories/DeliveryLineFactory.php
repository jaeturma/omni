<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\DeliveryLine;
use App\Models\SalesOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryLine>
 */
class DeliveryLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_id' => Delivery::factory(), 'sales_order_line_id' => SalesOrderLine::factory(), 'line_number' => 1, 'sku' => fake()->bothify('SKU-####'), 'description' => fake()->sentence(), 'uom_code' => 'PC', 'uom_name' => 'Piece', 'delivered_quantity' => '1.0000',
        ];
    }
}
