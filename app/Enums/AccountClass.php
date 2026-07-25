<?php

namespace App\Enums;

enum AccountClass: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case OwnerEquity = 'owner_equity';
    case Income = 'income';
    case CostOfSales = 'cost_of_sales';
    case Expense = 'expense';
    case OtherIncome = 'other_income';
    case OtherExpense = 'other_expense';

    public function normalBalance(): NormalBalance
    {
        return match ($this) {
            self::Asset, self::CostOfSales, self::Expense, self::OtherExpense => NormalBalance::Debit,
            self::Liability, self::OwnerEquity, self::Income, self::OtherIncome => NormalBalance::Credit,
        };
    }
}
