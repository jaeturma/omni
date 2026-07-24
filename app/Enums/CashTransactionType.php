<?php

namespace App\Enums;

enum CashTransactionType: string
{
    case CustomerReceipt = 'customer_receipt';
    case SupplierPayment = 'supplier_payment';
    case ExpensePayment = 'expense_payment';
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case PettyCashRelease = 'petty_cash_release';
    case PettyCashReturn = 'petty_cash_return';
    case PettyCashReplenishment = 'petty_cash_replenishment';
    case Adjustment = 'adjustment';
    case OpeningBalance = 'opening_balance';
}
