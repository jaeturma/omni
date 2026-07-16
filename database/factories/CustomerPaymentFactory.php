<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPayment>
 */
class CustomerPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['customer_id' => Customer::factory(), 'payment_method_id' => PaymentMethod::factory(),
            'payment_date' => '2026-07-17', 'gross_settlement_amount' => '100.0000',
            'withholding_amount' => '0.0000', 'other_deductions' => '0.0000', 'net_cash_received' => '100.0000',
            'unapplied_amount' => '100.0000', 'status' => 'draft', 'created_by' => User::factory(), 'updated_by' => User::factory()];
    }
}
