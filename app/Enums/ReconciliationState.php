<?php

namespace App\Enums;

enum ReconciliationState: string
{
    case Unreconciled = 'unreconciled';
    case Matched = 'matched';
    case Reconciled = 'reconciled';
    case Disputed = 'disputed';
}
