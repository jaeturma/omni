<?php

namespace App\Support;

use App\Enums\AccountType;
use App\Models\FiscalPeriod;
use DomainException;
use Illuminate\Support\Carbon;

final class AccountingWorkflow
{
    public const AMOUNT_SCALE = 4;

    public const BALANCING_TOLERANCE = '0.0000';

    public const ROUNDING_METHOD = 'round_half_up';

    public const POSTING_DATE_RULE = 'open_fiscal_period';

    public const DOCUMENT_DATE_RULE = 'preserve_source_document_date';

    public const SOURCE_POSTING_UNIQUE = true;

    public const RETAINED_EARNINGS_TYPE = AccountType::RetainedEarnings;

    public const OWNER_CAPITAL_TYPE = AccountType::OwnerCapital;

    public const OWNER_DRAWINGS_TYPE = AccountType::OwnerDrawings;

    public const PERMISSIONS = [
        'chart-of-accounts.view', 'chart-of-accounts.create', 'chart-of-accounts.update', 'chart-of-accounts.activate',
        'chart-of-accounts.deactivate', 'chart-of-accounts.view-balances',
        'journal-entries.view', 'journal-entries.create', 'journal-entries.update', 'journal-entries.post', 'journal-entries.reverse',
        'posting-rules.view', 'posting-rules.manage', 'accounting-posting.post', 'accounting-posting.reverse',
        'general-ledger.view', 'general-ledger.export', 'trial-balance.view', 'trial-balance.export',
        'subledger-reconciliation.view', 'accounting-periods.close', 'accounting-periods.lock', 'accounting-periods.reopen',
    ];

    public const ENCODER_PERMISSIONS = [
        'chart-of-accounts.view', 'journal-entries.view', 'journal-entries.create', 'journal-entries.update',
        'posting-rules.view', 'general-ledger.view', 'trial-balance.view', 'subledger-reconciliation.view',
    ];

    public const VIEW_PERMISSIONS = [
        'chart-of-accounts.view', 'journal-entries.view', 'posting-rules.view',
        'general-ledger.view', 'trial-balance.view', 'subledger-reconciliation.view',
    ];

    public static function isBalanced(string $totalDebits, string $totalCredits): bool
    {
        $difference = bcsub($totalDebits, $totalCredits, self::AMOUNT_SCALE);
        $absoluteDifference = bccomp($difference, '0', self::AMOUNT_SCALE) < 0
            ? bcmul($difference, '-1', self::AMOUNT_SCALE)
            : $difference;

        return bccomp($absoluteDifference, self::BALANCING_TOLERANCE, self::AMOUNT_SCALE) <= 0;
    }

    public static function assertPostingPeriod(FiscalPeriod $period, string $postingDate): void
    {
        $date = Carbon::parse($postingDate)->startOfDay();
        if ($period->status !== 'open') {
            throw new DomainException('The posting date must belong to an open fiscal period.');
        }
        if ($date->lt($period->starts_on) || $date->gt($period->ends_on)) {
            throw new DomainException('The posting date is outside the selected fiscal period.');
        }
    }

    private function __construct() {}
}
