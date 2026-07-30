<?php

namespace App\Enums;

enum AccountType: string
{
    case Cash = 'cash';
    case AccountsReceivable = 'accounts_receivable';
    case Inventory = 'inventory';
    case PrepaidExpense = 'prepaid_expense';
    case PropertyPlantEquipment = 'property_plant_equipment';
    case AccumulatedDepreciation = 'accumulated_depreciation';
    case AccountsPayable = 'accounts_payable';
    case AccruedLiability = 'accrued_liability';
    case TaxPayable = 'tax_payable';
    case LoansPayable = 'loans_payable';
    case OwnerCapital = 'owner_capital';
    case OwnerDrawings = 'owner_drawings';
    case RetainedEarnings = 'retained_earnings';
    case SalesIncome = 'sales_income';
    case SalesReturnsDiscounts = 'sales_returns_discounts';
    case ServiceIncome = 'service_income';
    case CostOfSales = 'cost_of_sales';
    case OperatingExpense = 'operating_expense';
    case OtherIncome = 'other_income';
    case OtherExpense = 'other_expense';

    public function accountClass(): AccountClass
    {
        return match ($this) {
            self::Cash, self::AccountsReceivable, self::Inventory, self::PrepaidExpense,
            self::PropertyPlantEquipment, self::AccumulatedDepreciation => AccountClass::Asset,
            self::AccountsPayable, self::AccruedLiability, self::TaxPayable, self::LoansPayable => AccountClass::Liability,
            self::OwnerCapital, self::OwnerDrawings, self::RetainedEarnings => AccountClass::OwnerEquity,
            self::SalesIncome, self::SalesReturnsDiscounts, self::ServiceIncome => AccountClass::Income,
            self::CostOfSales => AccountClass::CostOfSales,
            self::OperatingExpense => AccountClass::Expense,
            self::OtherIncome => AccountClass::OtherIncome,
            self::OtherExpense => AccountClass::OtherExpense,
        };
    }

    public function normalBalance(): NormalBalance
    {
        return match ($this) {
            self::OwnerDrawings, self::SalesReturnsDiscounts => NormalBalance::Debit,
            self::AccumulatedDepreciation => NormalBalance::Credit,
            default => $this->accountClass()->normalBalance(),
        };
    }

    public function defaultCurrentClassification(): ?CurrentClassification
    {
        return match ($this) {
            self::Cash, self::AccountsReceivable, self::Inventory, self::PrepaidExpense,
            self::AccountsPayable, self::AccruedLiability, self::TaxPayable => CurrentClassification::Current,
            self::PropertyPlantEquipment, self::AccumulatedDepreciation, self::LoansPayable => CurrentClassification::NonCurrent,
            default => null,
        };
    }

    public function defaultCashFlowClassification(): ?CashFlowClassification
    {
        return match ($this) {
            self::AccountsReceivable, self::Inventory, self::PrepaidExpense, self::AccountsPayable,
            self::AccruedLiability, self::TaxPayable, self::SalesIncome, self::SalesReturnsDiscounts,
            self::ServiceIncome, self::CostOfSales, self::OperatingExpense, self::OtherIncome,
            self::OtherExpense => CashFlowClassification::Operating,
            self::PropertyPlantEquipment, self::AccumulatedDepreciation => CashFlowClassification::Investing,
            self::LoansPayable, self::OwnerCapital, self::OwnerDrawings, self::RetainedEarnings => CashFlowClassification::Financing,
            self::Cash => null,
        };
    }
}
