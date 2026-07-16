<?php

namespace Database\Factories;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SalesInvoiceLine> */
class SalesInvoiceLineFactory extends Factory
{
    public function definition(): array
    {
        return ['sales_invoice_id' => SalesInvoice::factory(), 'line_number' => 1, 'item_type' => 'service', 'description' => fake()->sentence(), 'uom_code' => 'UNIT', 'uom_name' => 'Unit', 'quantity' => '1.0000', 'unit_price' => '100.0000', 'discount_rate' => '0.000000', 'gross_amount' => '100.0000', 'discount_amount' => '0.0000', 'net_amount' => '100.0000'];
    }
}
