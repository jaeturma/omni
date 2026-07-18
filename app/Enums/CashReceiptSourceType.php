<?php

namespace App\Enums;

enum CashReceiptSourceType: string
{
    case CustomerPayment = 'customer_payment';
    case OtherIncome = 'other_income';
    case OwnerCapital = 'owner_capital';
}
