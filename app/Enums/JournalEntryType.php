<?php

namespace App\Enums;

enum JournalEntryType: string
{
    case Opening = 'opening';
    case Sales = 'sales';
    case Collection = 'collection';
    case Purchase = 'purchase';
    case SupplierPayment = 'supplier_payment';
    case Expense = 'expense';
    case CashReceipt = 'cash_receipt';
    case CashDisbursement = 'cash_disbursement';
    case Transfer = 'transfer';
    case Inventory = 'inventory';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';
    case Closing = 'closing';
}
