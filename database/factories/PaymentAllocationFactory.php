<?php

namespace Database\Factories;

use App\Models\CustomerPayment;
use App\Models\PaymentAllocation;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAllocation>
 */
class PaymentAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['customer_payment_id' => CustomerPayment::factory(), 'sales_invoice_id' => SalesInvoice::factory(),
            'amount' => '50.0000', 'status' => 'active', 'allocated_at' => now(), 'allocated_by' => User::factory()];
    }
}
