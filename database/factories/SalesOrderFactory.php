<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(), 'order_date' => '2026-07-16', 'payment_terms' => 30, 'customer_name' => fake()->company(), 'billing_address' => fake()->address(), 'delivery_address' => fake()->address(), 'document_discount_rate' => '0.000000', 'subtotal' => '0.0000', 'line_discount_total' => '0.0000', 'document_discount_amount' => '0.0000', 'grand_total' => '0.0000', 'status' => 'draft', 'created_by' => User::factory(), 'updated_by' => User::factory(),
        ];
    }
}
