<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(), 'quotation_date' => '2026-07-16', 'valid_until' => '2026-08-15',
            'customer_name' => fake()->company(), 'customer_tin' => null, 'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(), 'contact_phone' => fake()->phoneNumber(),
            'billing_address' => fake()->address(), 'delivery_address' => fake()->address(),
            'document_discount_rate' => '0.000000', 'subtotal' => '0.0000', 'line_discount_total' => '0.0000',
            'document_discount_amount' => '0.0000', 'grand_total' => '0.0000', 'status' => 'draft',
            'created_by' => User::factory(), 'updated_by' => User::factory(),
        ];
    }
}
