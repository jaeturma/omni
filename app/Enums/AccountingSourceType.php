<?php

namespace App\Enums;

enum AccountingSourceType: string
{
    case SalesInvoice = 'sales_invoice';
    case CustomerPayment = 'customer_payment';
    case SupplierInvoice = 'supplier_invoice';
    case SupplierPayment = 'supplier_payment';
    case Expense = 'expense';
    case CashReceipt = 'cash_receipt';
    case CashDisbursement = 'cash_disbursement';
    case FundTransfer = 'fund_transfer';
    case InventoryMovement = 'inventory_movement';
    case Manual = 'manual';
}
