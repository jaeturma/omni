<?php

namespace App\Enums;

enum BankReconciliationStatus: string
{
    case Draft = 'draft';
    case Reviewed = 'reviewed';
    case Finalized = 'finalized';
    case Reopened = 'reopened';
}
