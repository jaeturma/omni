<?php

namespace Database\Factories;

use App\Models\CashDisbursement;
use App\Models\FinancialAccount;
use App\Models\FiscalPeriod;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CashDisbursement> */
class CashDisbursementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'disbursement_date' => '2026-07-24', 'fiscal_period_id' => FiscalPeriod::factory(),
            'financial_account_id' => FinancialAccount::factory(), 'source_type' => 'other_approved',
            'payee' => fake()->name(), 'payment_method_id' => PaymentMethod::factory(),
            'gross_settlement' => '100.0000', 'deductions_or_bank_charges' => '0.0000',
            'net_cash_out' => '100.0000', 'created_by' => User::factory(), 'updated_by' => User::factory(),
        ];
    }
}
