<?php

namespace App\Enums;

enum CashFlowClassification: string
{
    case Operating = 'operating';
    case Investing = 'investing';
    case Financing = 'financing';
}
