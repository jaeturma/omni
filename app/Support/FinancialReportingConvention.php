<?php

namespace App\Support;

use App\Enums\AccountClass;
use App\Enums\JournalEntryStatus;
use App\Enums\NormalBalance;
use App\Enums\ReportBalanceBasis;

final class FinancialReportingConvention
{
    public const SOURCE_STATUSES = [JournalEntryStatus::Posted, JournalEntryStatus::Reversed];

    public const DECIMAL_SCALE = 4;

    public const DISPLAY_SCALE = 2;

    public const ROUNDING_METHOD = 'round_half_up';

    public const SUBTOTAL_RULE = 'round_unrounded_subtotal';

    public const ZERO_BALANCES_VISIBLE_BY_DEFAULT = false;

    public const COMPARATIVE_MAPPING_RULE = 'same_account_mapping_and_basis';

    public const OUTPUT_PARAMETER_RULE = 'print_and_export_include_all_parameters';

    public const TRIAL_BALANCE_READINESS_RULE = 'balanced_trial_balance_required';

    public const PERMISSIONS = [
        'financial-reports.view',
        'financial-reports.export',
        'financial-reports.view-sensitive',
        'financial-report-settings.manage',
        'income-statement.view',
        'income-statement.export',
        'income-statement.drilldown',
        'balance-sheet.view',
        'balance-sheet.export',
        'balance-sheet.drilldown',
    ];

    public const VIEW_PERMISSIONS = ['financial-reports.view', 'income-statement.view', 'balance-sheet.view'];

    public static function balanceBasis(AccountClass $accountClass): ReportBalanceBasis
    {
        return match ($accountClass) {
            AccountClass::Asset, AccountClass::Liability, AccountClass::OwnerEquity => ReportBalanceBasis::Cumulative,
            AccountClass::Income, AccountClass::CostOfSales, AccountClass::Expense,
            AccountClass::OtherIncome, AccountClass::OtherExpense => ReportBalanceBasis::PeriodActivity,
        };
    }

    public static function present(string $debit, string $credit, NormalBalance $normalBalance): string
    {
        return $normalBalance === NormalBalance::Debit
            ? bcsub($debit, $credit, self::DECIMAL_SCALE)
            : bcsub($credit, $debit, self::DECIMAL_SCALE);
    }

    public static function round(string $amount): string
    {
        $increment = bccomp($amount, '0', self::DECIMAL_SCALE) < 0 ? '-0.005' : '0.005';

        return bcadd($amount, $increment, self::DISPLAY_SCALE);
    }

    /** @param iterable<string> $amounts */
    public static function subtotal(iterable $amounts): string
    {
        $subtotal = '0.0000';
        foreach ($amounts as $amount) {
            $subtotal = bcadd($subtotal, $amount, self::DECIMAL_SCALE);
        }

        return self::round($subtotal);
    }

    private function __construct() {}
}
