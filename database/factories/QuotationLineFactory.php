<?php

namespace Database\Factories;

use App\Models\ProductService;
use App\Models\Quotation;
use App\Models\QuotationLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationLine>
 */
class QuotationLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quotation_id' => Quotation::factory(), 'product_service_id' => ProductService::factory(),
            'line_number' => 1, 'item_type' => 'product', 'sku' => fake()->unique()->bothify('SKU-#####'),
            'description' => fake()->sentence(), 'uom_code' => 'PC', 'uom_name' => 'Piece',
            'quantity' => '1.0000', 'unit_price' => '100.0000', 'discount_rate' => '0.000000',
            'gross_amount' => '100.0000', 'discount_amount' => '0.0000', 'net_amount' => '100.0000',
        ];
    }
}
