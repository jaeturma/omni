<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sales_order_id' => SalesOrder::factory(), 'customer_id' => Customer::factory(), 'delivery_date' => '2026-07-16', 'customer_name' => fake()->company(), 'delivery_address' => fake()->address(), 'status' => 'draft', 'created_by' => User::factory(), 'updated_by' => User::factory(),
        ];
    }
}
