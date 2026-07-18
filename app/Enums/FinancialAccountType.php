<?php

namespace App\Enums;

enum FinancialAccountType: string
{
    case CashOnHand = 'cash_on_hand';
    case PettyCash = 'petty_cash';
    case BankChecking = 'bank_checking';
    case BankSavings = 'bank_savings';
    case EWallet = 'e_wallet';
    case ClearingAccount = 'clearing_account';
    case OtherCashEquivalent = 'other_cash_equivalent';
}
