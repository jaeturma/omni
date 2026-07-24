<?php

namespace App\Enums;

enum CashDisbursementSourceType: string
{
    case SupplierPayment = 'supplier_payment';
    case Expense = 'expense';
    case OtherApproved = 'other_approved';
}
