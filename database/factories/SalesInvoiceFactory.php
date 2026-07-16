<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SalesInvoice> */
class SalesInvoiceFactory extends Factory
{
    public function definition(): array
    {
        return ['customer_id' => Customer::factory(), 'fiscal_period_id' => FiscalPeriod::factory(), 'invoice_date' => '2026-07-16', 'due_date' => '2026-08-15', 'customer_name' => fake()->company(), 'billing_address' => fake()->address(), 'source_type' => 'direct', 'gross_amount' => '100.0000', 'discount_amount' => '0.0000', 'net_sales_amount' => '100.0000', 'expected_withholding_amount' => '0.0000', 'total_receivable' => '100.0000', 'paid_amount' => '0.0000', 'balance_due' => '100.0000', 'status' => 'draft', 'created_by' => User::factory(), 'updated_by' => User::factory()];
    }
}
