<?php

namespace App\Enums;

enum ReportBalanceBasis: string
{
    case PeriodActivity = 'period_activity';
    case Cumulative = 'cumulative';
}
