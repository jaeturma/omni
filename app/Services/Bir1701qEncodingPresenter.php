<?php

namespace App\Services;

use App\Models\Bir1701qWorksheet;

class Bir1701qEncodingPresenter
{
    public const MONEY_FIELDS = [
        'cumulative_gross_sales', 'sales_returns_discounts', 'net_sales', 'cost_of_sales', 'other_income',
        'gross_income', 'financial_itemized_deductions', 'osd_deduction', 'manual_deduction_adjustment',
        'taxable_income_adjustment', 'taxable_income', 'income_tax_due', 'prior_quarter_payments',
        'verified_creditable_withholding', 'manual_creditable_withholding', 'other_allowable_credits',
        'surcharge', 'interest', 'compromise_penalty', 'total_amount_payable',
    ];

    /** @return array<string, array{exact: string, whole_peso: string}> */
    public function amounts(Bir1701qWorksheet $worksheet): array
    {
        return collect(self::MONEY_FIELDS)->mapWithKeys(fn (string $field): array => [
            $field => [
                'exact' => (string) $worksheet->getAttribute($field),
                'whole_peso' => $this->wholePeso((string) $worksheet->getAttribute($field)),
            ],
        ])->all();
    }

    public function wholePeso(string $amount): string
    {
        $adjusted = bccomp($amount, '0', 4) < 0
            ? bcsub($amount, '0.5000', 4)
            : bcadd($amount, '0.5000', 4);

        return bcdiv($adjusted, '1', 0);
    }
}
