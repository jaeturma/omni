<?php

use App\Enums\BankReconciliationStatus;
use App\Enums\CashTransactionStatus;
use App\Enums\CashTransactionType;
use App\Enums\ReconciliationState;
use App\Models\BankReconciliation;
use App\Models\BankStatementImport;
use App\Models\CashTransaction;
use App\Models\FinancialAccount;
use App\Models\User;
use App\Reports\CashPositionReport;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('Bookkeeper');
    $this->account = FinancialAccount::factory()->create(['opening_balance' => '1000.0000', 'opening_balance_date' => '2026-06-01', 'current_balance' => '1000.0000']);
});

function cashReportFilters(array $changes = []): array
{
    return array_merge(['start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'as_of' => '2026-07-31',
        'financial_account_id' => null, 'account_type' => null, 'transaction_type' => null], $changes);
}

function reportTransaction(FinancialAccount $account, User $user, CashTransactionType $type, string $amount, string $date, CashTransactionStatus $status = CashTransactionStatus::Posted, string $fee = '0.0000'): CashTransaction
{
    return CashTransaction::query()->create(['financial_account_id' => $account->id, 'type' => $type, 'transaction_date' => $date,
        'amount' => $amount, 'fee_amount' => $fee, 'status' => $status, 'posted_at' => $status === CashTransactionStatus::Posted ? now() : null,
        'posted_by' => $status === CashTransactionStatus::Posted ? $user->id : null, 'created_by' => $user->id]);
}

test('opening movement and closing balances use posted effective-dated activity', function () {
    reportTransaction($this->account, $this->user, CashTransactionType::Deposit, '50.0000', '2026-06-30');
    reportTransaction($this->account, $this->user, CashTransactionType::CustomerReceipt, '100.0000', '2026-07-10');
    reportTransaction($this->account, $this->user, CashTransactionType::Withdrawal, '40.0000', '2026-07-11');

    $summary = app(CashPositionReport::class)->summary(cashReportFilters(), true);
    $position = $summary['positions']->first();

    expect($position['opening'])->toBe('1050.0000')->and($position['movement'])->toBe('60.0000')
        ->and($position['closing'])->toBe('1110.0000')->and($summary['receipts'])->toBe('100.0000')
        ->and($summary['disbursements'])->toBe('40.0000');
});

test('as-of date excludes future and voided transactions', function () {
    reportTransaction($this->account, $this->user, CashTransactionType::Deposit, '100.0000', '2026-07-10');
    reportTransaction($this->account, $this->user, CashTransactionType::Deposit, '999.0000', '2026-07-12', CashTransactionStatus::Voided);
    reportTransaction($this->account, $this->user, CashTransactionType::Deposit, '500.0000', '2026-08-01');

    $summary = app(CashPositionReport::class)->summary(cashReportFilters(), true);

    expect($summary['positions']->first()['as_of'])->toBe('1100.0000')
        ->and($summary['receipts'])->toBe('100.0000');
});

test('transfer principal is neutral across all accounts', function () {
    $destination = FinancialAccount::factory()->create(['opening_balance' => '500.0000', 'opening_balance_date' => '2026-06-01']);
    reportTransaction($this->account, $this->user, CashTransactionType::TransferOut, '200.0000', '2026-07-15');
    reportTransaction($destination, $this->user, CashTransactionType::TransferIn, '200.0000', '2026-07-15');

    $summary = app(CashPositionReport::class)->summary(cashReportFilters(), true);

    expect($summary['source_movements']['transfer_out'])->toBe('-200.0000')
        ->and($summary['source_movements']['transfer_in'])->toBe('200.0000')
        ->and($summary['net_movement'])->toBe('0.0000');
});

test('account and transaction type filters constrain report activity', function () {
    $other = FinancialAccount::factory()->create();
    reportTransaction($this->account, $this->user, CashTransactionType::Deposit, '75.0000', '2026-07-10');
    reportTransaction($this->account, $this->user, CashTransactionType::Withdrawal, '25.0000', '2026-07-10');
    reportTransaction($other, $this->user, CashTransactionType::Deposit, '500.0000', '2026-07-10');

    $filters = cashReportFilters(['financial_account_id' => $this->account->id, 'transaction_type' => CashTransactionType::Deposit->value]);
    $summary = app(CashPositionReport::class)->summary($filters, true);

    expect($summary['positions'])->toHaveCount(1)->and($summary['receipts'])->toBe('75.0000')
        ->and($summary['disbursements'])->toBe('0.0000')
        ->and(app(CashPositionReport::class)->activityPaginator($filters))->toHaveCount(1);
});

test('reconciled and unreconciled amounts and statement items remain separate', function () {
    $matched = reportTransaction($this->account, $this->user, CashTransactionType::Deposit, '100.0000', '2026-07-10');
    reportTransaction($this->account, $this->user, CashTransactionType::Deposit, '25.0000', '2026-07-11');
    $import = BankStatementImport::query()->create(['financial_account_id' => $this->account->id, 'statement_start_date' => '2026-07-01',
        'statement_end_date' => '2026-07-31', 'source_filename' => 'bank.csv', 'file_hash' => fake()->sha256(), 'column_mapping' => [],
        'imported_by' => $this->user->id, 'imported_at' => now()]);
    $matchedLine = $import->lines()->create(['line_number' => 2, 'transaction_date' => '2026-07-10', 'posting_date' => '2026-07-10',
        'description' => 'Matched', 'debit' => 0, 'credit' => 100, 'normalized_amount' => 100, 'match_status' => ReconciliationState::Reconciled, 'original_values' => []]);
    $import->lines()->create(['line_number' => 3, 'transaction_date' => '2026-07-12', 'posting_date' => '2026-07-12',
        'description' => 'Unmatched bank item', 'debit' => 10, 'credit' => 0, 'normalized_amount' => -10, 'original_values' => []]);
    $reconciliation = BankReconciliation::query()->create(['bank_statement_import_id' => $import->id, 'financial_account_id' => $this->account->id,
        'statement_start_date' => '2026-07-01', 'statement_end_date' => '2026-07-31', 'statement_opening_balance' => 1000,
        'statement_closing_balance' => 1115, 'system_opening_balance' => 1000, 'system_closing_balance' => 1125,
        'reconciliation_difference' => 0, 'status' => BankReconciliationStatus::Finalized, 'created_by' => $this->user->id]);
    $reconciliation->matches()->create(['bank_statement_line_id' => $matchedLine->id, 'cash_transaction_id' => $matched->id,
        'matched_amount' => 100, 'confirmed_by' => $this->user->id, 'confirmed_at' => now()]);

    $summary = app(CashPositionReport::class)->summary(cashReportFilters(), true);

    expect($summary['reconciled'])->toBe('100.0000')->and($summary['unreconciled'])->toBe('25.0000');
    expect($summary['unreconciled_items'])->toHaveCount(1);
    expect($summary['reconciliation_history'])->toHaveCount(1);
});

test('confirmed draft match remains unreconciled until finalization', function () {
    $matched = reportTransaction($this->account, $this->user, CashTransactionType::Deposit, '100.0000', '2026-07-10');
    $import = BankStatementImport::query()->create(['financial_account_id' => $this->account->id, 'statement_start_date' => '2026-07-01',
        'statement_end_date' => '2026-07-31', 'source_filename' => 'draft.csv', 'file_hash' => fake()->sha256(), 'column_mapping' => [],
        'imported_by' => $this->user->id, 'imported_at' => now()]);
    $line = $import->lines()->create(['line_number' => 2, 'transaction_date' => '2026-07-10', 'posting_date' => '2026-07-10',
        'description' => 'Confirmed only', 'debit' => 0, 'credit' => 100, 'normalized_amount' => 100,
        'match_status' => ReconciliationState::Matched, 'original_values' => []]);
    $reconciliation = BankReconciliation::query()->create(['bank_statement_import_id' => $import->id, 'financial_account_id' => $this->account->id,
        'statement_start_date' => '2026-07-01', 'statement_end_date' => '2026-07-31', 'statement_opening_balance' => 1000,
        'statement_closing_balance' => 1100, 'system_opening_balance' => 1000, 'system_closing_balance' => 1100,
        'reconciliation_difference' => 0, 'status' => BankReconciliationStatus::Draft, 'created_by' => $this->user->id]);
    $reconciliation->matches()->create(['bank_statement_line_id' => $line->id, 'cash_transaction_id' => $matched->id,
        'matched_amount' => 100, 'confirmed_by' => $this->user->id, 'confirmed_at' => now()]);

    $summary = app(CashPositionReport::class)->summary(cashReportFilters(), true);

    expect($summary['reconciled'])->toBe('0.0000')->and($summary['unreconciled'])->toBe('100.0000');
});

test('report pages csv and permissions are enforced', function () {
    reportTransaction($this->account, $this->user, CashTransactionType::Deposit, '100.0000', '2026-07-10');
    $query = cashReportFilters();
    $this->actingAs($this->user)->get(route('cash-reports.index', $query))->assertOk()->assertSee('Cash Position and Activity');
    $this->get(route('cash-reports.print', $query))->assertOk()->assertSee('Cash Position Report');
    $this->get(route('cash-reports.export', $query))->assertSuccessful()->assertDownload();

    $unauthorized = User::factory()->create();
    $this->actingAs($unauthorized)->get(route('cash-reports.index', $query))->assertForbidden();
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('cash-reports.index', $query))->assertOk();
    $this->get(route('cash-reports.export', $query))->assertForbidden();
});

test('invalid date range is rejected', function () {
    $this->actingAs($this->user)->get(route('cash-reports.index', cashReportFilters([
        'start_date' => '2026-07-31', 'end_date' => '2026-07-01', 'as_of' => '2026-06-30',
    ])))->assertSessionHasErrors(['end_date', 'as_of']);
});
