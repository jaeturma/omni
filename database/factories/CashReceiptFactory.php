<?php

namespace Database\Factories;

use App\Models\CashReceipt;
use App\Models\FinancialAccount;
use App\Models\FiscalPeriod;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashReceipt>
 */
class CashReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['receipt_date' => '2026-07-18', 'fiscal_period_id' => FiscalPeriod::factory(), 'financial_account_id' => FinancialAccount::factory(),
            'source_type' => 'other_income', 'payer_name' => fake()->name(), 'payment_method_id' => PaymentMethod::factory(),
            'gross_receipt' => '100.0000', 'deductions_or_fees' => '0.0000', 'net_amount_deposited' => '100.0000',
            'created_by' => User::factory(), 'updated_by' => User::factory()];
    }
}
