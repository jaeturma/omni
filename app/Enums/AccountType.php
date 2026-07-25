<?php

namespace App\Enums;

enum AccountType: string
{
    case Cash = 'cash';
    case AccountsReceivable = 'accounts_receivable';
    case Inventory = 'inventory';
    case PrepaidExpense = 'prepaid_expense';
    case PropertyPlantEquipment = 'property_plant_equipment';
    case AccountsPayable = 'accounts_payable';
    case AccruedLiability = 'accrued_liability';
    case TaxPayable = 'tax_payable';
    case OwnerCapital = 'owner_capital';
    case OwnerDrawings = 'owner_drawings';
    case RetainedEarnings = 'retained_earnings';
    case SalesIncome = 'sales_income';
    case ServiceIncome = 'service_income';
    case CostOfSales = 'cost_of_sales';
    case OperatingExpense = 'operating_expense';
    case OtherIncome = 'other_income';
    case OtherExpense = 'other_expense';

    public function accountClass(): AccountClass
    {
        return match ($this) {
            self::Cash, self::AccountsReceivable, self::Inventory, self::PrepaidExpense,
            self::PropertyPlantEquipment => AccountClass::Asset,
            self::AccountsPayable, self::AccruedLiability, self::TaxPayable => AccountClass::Liability,
            self::OwnerCapital, self::OwnerDrawings, self::RetainedEarnings => AccountClass::OwnerEquity,
            self::SalesIncome, self::ServiceIncome => AccountClass::Income,
            self::CostOfSales => AccountClass::CostOfSales,
            self::OperatingExpense => AccountClass::Expense,
            self::OtherIncome => AccountClass::OtherIncome,
            self::OtherExpense => AccountClass::OtherExpense,
        };
    }

    public function normalBalance(): NormalBalance
    {
        return $this === self::OwnerDrawings
            ? NormalBalance::Debit
            : $this->accountClass()->normalBalance();
    }
}
