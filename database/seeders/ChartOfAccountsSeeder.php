<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['1000', 'Assets', AccountType::Cash],
            ['2000', 'Liabilities', AccountType::AccountsPayable],
            ['3000', "Owner's Equity", AccountType::OwnerCapital],
            ['4000', 'Income', AccountType::SalesIncome],
            ['5000', 'Cost of Sales', AccountType::CostOfSales],
            ['6000', 'Expenses', AccountType::OperatingExpense],
        ];

        $parents = [];
        foreach ($groups as [$code, $name, $type]) {
            $parents[$code] = $this->seedAccount($code, $name, $type, null, true);
        }

        $accounts = [
            ['1010', 'Cash on Hand', AccountType::Cash, '1000', 'cash_on_hand'],
            ['1020', 'Petty Cash', AccountType::Cash, '1000', 'petty_cash'],
            ['1030', 'Cash in Bank', AccountType::Cash, '1000', 'cash_in_bank'],
            ['1040', 'E-Wallet', AccountType::Cash, '1000', 'e_wallet'],
            ['1100', 'Accounts Receivable', AccountType::AccountsReceivable, '1000', 'accounts_receivable'],
            ['1200', 'Inventory', AccountType::Inventory, '1000', 'inventory'],
            ['1300', 'Creditable Withholding Tax', AccountType::PrepaidExpense, '1000', 'creditable_withholding_tax'],
            ['1400', 'Prepaid Expenses', AccountType::PrepaidExpense, '1000', null],
            ['1500', 'Office Equipment', AccountType::PropertyPlantEquipment, '1000', null],
            ['1510', 'Computer Equipment', AccountType::PropertyPlantEquipment, '1000', null],
            ['1590', 'Accumulated Depreciation', AccountType::AccumulatedDepreciation, '1000', null],
            ['2010', 'Accounts Payable', AccountType::AccountsPayable, '2000', 'accounts_payable'],
            ['2020', 'Accrued Expenses', AccountType::AccruedLiability, '2000', null],
            ['2030', 'Percentage Tax Payable', AccountType::TaxPayable, '2000', 'percentage_tax_payable'],
            ['2040', 'Withholding Tax Payable', AccountType::TaxPayable, '2000', 'withholding_tax_payable'],
            ['2100', 'Loans Payable', AccountType::LoansPayable, '2000', null],
            ['3010', "Owner's Capital", AccountType::OwnerCapital, '3000', 'owner_capital'],
            ['3020', "Owner's Drawings", AccountType::OwnerDrawings, '3000', 'owner_drawings'],
            ['3030', 'Current Year Earnings', AccountType::RetainedEarnings, '3000', 'current_year_earnings'],
            ['3040', 'Prior-Year Equity', AccountType::RetainedEarnings, '3000', 'retained_earnings'],
            ['4010', 'ICT Product Sales', AccountType::SalesIncome, '4000', null],
            ['4020', 'Office Supply Sales', AccountType::SalesIncome, '4000', null],
            ['4030', 'School Supply Sales', AccountType::SalesIncome, '4000', null],
            ['4040', 'Service Income', AccountType::ServiceIncome, '4000', null],
            ['4050', 'Installation Income', AccountType::ServiceIncome, '4000', null],
            ['4060', 'Other Business Income', AccountType::OtherIncome, '4000', null],
            ['4090', 'Sales Returns and Discounts', AccountType::SalesReturnsDiscounts, '4000', null],
            ['5010', 'ICT Product Cost', AccountType::CostOfSales, '5000', null],
            ['5020', 'Office Supply Cost', AccountType::CostOfSales, '5000', null],
            ['5030', 'School Supply Cost', AccountType::CostOfSales, '5000', null],
            ['5040', 'Freight-In', AccountType::CostOfSales, '5000', null],
            ['5050', 'Direct Service Cost', AccountType::CostOfSales, '5000', null],
            ['6010', 'Internet and Communication', AccountType::OperatingExpense, '6000', null],
            ['6020', 'Utilities', AccountType::OperatingExpense, '6000', null],
            ['6030', 'Rent', AccountType::OperatingExpense, '6000', null],
            ['6040', 'Fuel and Transportation', AccountType::OperatingExpense, '6000', null],
            ['6050', 'Delivery and Freight', AccountType::OperatingExpense, '6000', null],
            ['6060', 'Repairs and Maintenance', AccountType::OperatingExpense, '6000', null],
            ['6070', 'Salaries and Wages', AccountType::OperatingExpense, '6000', null],
            ['6080', 'Professional Fees', AccountType::OperatingExpense, '6000', null],
            ['6090', 'Bank Charges', AccountType::OperatingExpense, '6000', null],
            ['6100', 'Taxes and Licenses', AccountType::OperatingExpense, '6000', null],
            ['6110', 'Advertising', AccountType::OperatingExpense, '6000', null],
            ['6120', 'Office Supplies Expense', AccountType::OperatingExpense, '6000', null],
            ['6130', 'Software and Hosting', AccountType::OperatingExpense, '6000', null],
            ['6140', 'Meals and Representation', AccountType::OperatingExpense, '6000', null],
            ['6150', 'Depreciation', AccountType::OperatingExpense, '6000', null],
            ['6190', 'Miscellaneous Expense', AccountType::OperatingExpense, '6000', null],
            ['6200', 'Income Tax Expense', AccountType::IncomeTaxExpense, '6000', null],
        ];

        foreach ($accounts as $index => [$code, $name, $type, $parentCode, $controlType]) {
            $this->seedAccount($code, $name, $type, $parents[$parentCode]->id, false, $controlType, $index + 10);
        }
    }

    private function seedAccount(string $code, string $name, AccountType $type, ?int $parentId, bool $header, ?string $controlType = null, int $order = 0): Account
    {
        return Account::query()->updateOrCreate(['code' => $code], [
            'name' => $name,
            'account_class' => $type->accountClass(),
            'account_type' => $type,
            'normal_balance' => $type->normalBalance(),
            'current_classification' => $type->defaultCurrentClassification(),
            'cash_flow_classification' => $type->defaultCashFlowClassification(),
            'parent_id' => $parentId,
            'is_header' => $header,
            'is_postable' => ! $header,
            'is_control_account' => $controlType !== null,
            'control_account_type' => $controlType,
            'is_active' => true,
            'is_system' => true,
            'description' => null,
            'display_order' => $order,
        ]);
    }
}
