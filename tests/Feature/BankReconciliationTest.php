<?php

use App\Enums\CashTransactionStatus;
use App\Enums\CashTransactionType;
use App\Enums\ReconciliationState;
use App\Models\BankReconciliation;
use App\Models\BankStatementImport;
use App\Models\CashTransaction;
use App\Models\DocumentSequence;
use App\Models\FinancialAccount;
use App\Models\FiscalYear;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('Bookkeeper');
    $this->account = FinancialAccount::factory()->create(['opening_balance' => '1000.0000', 'current_balance' => '1000.0000', 'allow_reconciliation' => true]);
    $fiscalYear = FiscalYear::factory()->create();
    DocumentSequence::query()->create(['business_profile_id' => $fiscalYear->business_profile_id, 'fiscal_year_id' => $fiscalYear->id,
        'fiscal_year_scope' => $fiscalYear->id, 'document_type' => 'cash_adjustment', 'prefix' => 'ADJ-', 'current_number' => 0,
        'padding' => 6, 'reset_rule' => 'fiscal_year', 'active' => true, 'created_by' => $this->user->id, 'updated_by' => $this->user->id]);
});

function reconciliationStatement(FinancialAccount $account, User $user, array $amounts): BankStatementImport
{
    $import = BankStatementImport::query()->create(['financial_account_id' => $account->id, 'statement_start_date' => '2026-07-01',
        'statement_end_date' => '2026-07-31', 'source_filename' => 'statement.csv', 'file_hash' => fake()->sha256(),
        'column_mapping' => [], 'imported_by' => $user->id, 'imported_at' => now()]);
    foreach ($amounts as $index => $amount) {
        $import->lines()->create(['line_number' => $index + 2, 'transaction_date' => '2026-07-10', 'posting_date' => '2026-07-10',
            'description' => 'Statement item '.($index + 1), 'reference_number' => 'REF-'.($index + 1),
            'debit' => str_starts_with($amount, '-') ? str_replace('-', '', $amount) : 0, 'credit' => str_starts_with($amount, '-') ? 0 : $amount,
            'normalized_amount' => $amount, 'original_values' => []]);
    }

    return $import;
}

function postedTransaction(FinancialAccount $account, User $user, string $amount, string $date = '2026-07-10', ?string $reference = null): CashTransaction
{
    return CashTransaction::query()->create(['financial_account_id' => $account->id, 'type' => CashTransactionType::Deposit,
        'transaction_date' => $date, 'amount' => $amount, 'reference_number' => $reference, 'status' => CashTransactionStatus::Posted,
        'posted_at' => now(), 'posted_by' => $user->id, 'created_by' => $user->id]);
}

function startReconciliation($test, BankStatementImport $import, string $closing): BankReconciliation
{
    $test->actingAs($test->user)->post(route('bank-reconciliations.store'), ['bank_statement_import_id' => $import->id,
        'statement_opening_balance' => '1000.0000', 'statement_closing_balance' => $closing])->assertSessionHasNoErrors();

    return BankReconciliation::query()->firstOrFail();
}

test('exact match requires confirmation and clears the transparent difference', function () {
    $import = reconciliationStatement($this->account, $this->user, ['100.0000']);
    $transaction = postedTransaction($this->account, $this->user, '100.0000', reference: 'REF-1');
    $reconciliation = startReconciliation($this, $import, '1100.0000');
    expect($import->lines()->first()->match_status)->toBe(ReconciliationState::Unreconciled)
        ->and($reconciliation->reconciliation_difference)->toBe('-100.0000');

    $this->actingAs($this->user)->post(route('bank-reconciliations.matches.store', $reconciliation), [
        'bank_statement_line_id' => $import->lines()->value('id'), 'cash_transaction_ids' => [$transaction->id],
    ])->assertSessionHasNoErrors();

    expect($import->lines()->first()->match_status)->toBe(ReconciliationState::Matched)
        ->and($reconciliation->fresh()->reconciliation_difference)->toBe('0.0000');
});

test('date tolerance suggests a matching posted transaction without confirming it', function () {
    $import = reconciliationStatement($this->account, $this->user, ['50.0000']);
    postedTransaction($this->account, $this->user, '50.0000', '2026-07-13', 'TOLERANCE-REF');
    $reconciliation = startReconciliation($this, $import, '1050.0000');

    $this->actingAs($this->user)->get(route('bank-reconciliations.show', $reconciliation))->assertOk()->assertSee('TOLERANCE-REF');
    expect($import->lines()->first()->match_status)->toBe(ReconciliationState::Unreconciled);
});

test('one statement line can confirm a limited one-to-many match', function () {
    $import = reconciliationStatement($this->account, $this->user, ['100.0000']);
    $first = postedTransaction($this->account, $this->user, '60.0000');
    $second = postedTransaction($this->account, $this->user, '40.0000');
    $reconciliation = startReconciliation($this, $import, '1100.0000');

    $this->actingAs($this->user)->post(route('bank-reconciliations.matches.store', $reconciliation), [
        'bank_statement_line_id' => $import->lines()->value('id'), 'cash_transaction_ids' => [$first->id, $second->id],
    ])->assertSessionHasNoErrors();

    expect($reconciliation->matches()->count())->toBe(2);
});

test('mismatched total is rejected and remains unmatched', function () {
    $import = reconciliationStatement($this->account, $this->user, ['100.0000']);
    $transaction = postedTransaction($this->account, $this->user, '90.0000');
    $reconciliation = startReconciliation($this, $import, '1090.0000');

    $this->actingAs($this->user)->post(route('bank-reconciliations.matches.store', $reconciliation), [
        'bank_statement_line_id' => $import->lines()->value('id'), 'cash_transaction_ids' => [$transaction->id],
    ])->assertSessionHasErrors('cash_transaction_ids');

    expect($reconciliation->matches()->count())->toBe(0)
        ->and($reconciliation->unmatched_deposits)->toBe('100.0000');
});

test('explicit bank charge adjustment is posted and attributed without ledger entries', function () {
    $import = reconciliationStatement($this->account, $this->user, ['-25.0000']);
    $reconciliation = startReconciliation($this, $import, '975.0000');

    $this->actingAs($this->user)->post(route('bank-reconciliations.adjustments.store', $reconciliation), [
        'bank_statement_line_id' => $import->lines()->value('id'), 'kind' => 'bank_charge',
    ])->assertSessionHasNoErrors();

    $transaction = CashTransaction::query()->firstOrFail();
    expect($transaction->type)->toBe(CashTransactionType::Adjustment)
        ->and($transaction->amount)->toBe('-25.0000')
        ->and($reconciliation->fresh()->bank_charges)->toBe('25.0000')
        ->and($this->account->fresh()->current_balance)->toBe('975.0000');
});

test('finalization controls lock matches and reopening requires a reason', function () {
    $import = reconciliationStatement($this->account, $this->user, ['100.0000']);
    $transaction = postedTransaction($this->account, $this->user, '100.0000');
    $reconciliation = startReconciliation($this, $import, '1100.0000');
    $this->actingAs($this->user)->post(route('bank-reconciliations.matches.store', $reconciliation), [
        'bank_statement_line_id' => $import->lines()->value('id'), 'cash_transaction_ids' => [$transaction->id],
    ]);
    $this->patch(route('bank-reconciliations.transition', $reconciliation), ['transition' => 'review']);
    $this->patch(route('bank-reconciliations.transition', $reconciliation), ['transition' => 'finalize'])->assertSessionHasNoErrors();
    expect($import->fresh()->finalized_at)->not->toBeNull()
        ->and($import->lines()->first()->match_status)->toBe(ReconciliationState::Reconciled);

    $this->post(route('bank-reconciliations.matches.store', $reconciliation), [
        'bank_statement_line_id' => $import->lines()->value('id'), 'cash_transaction_ids' => [$transaction->id],
    ])->assertForbidden();
    $this->patch(route('bank-reconciliations.transition', $reconciliation), ['transition' => 'reopen'])->assertSessionHasErrors('reason');
    $this->patch(route('bank-reconciliations.transition', $reconciliation), ['transition' => 'reopen', 'reason' => 'Correcting confirmed evidence'])->assertSessionHasNoErrors();
    expect($import->fresh()->finalized_at)->toBeNull()
        ->and($import->lines()->first()->match_status)->toBe(ReconciliationState::Matched);
});

test('non-zero difference requires a documented finalization exception', function () {
    $import = reconciliationStatement($this->account, $this->user, ['25.0000']);
    $reconciliation = startReconciliation($this, $import, '1000.0000');
    $this->actingAs($this->user)->patch(route('bank-reconciliations.transition', $reconciliation), ['transition' => 'review']);

    $this->patch(route('bank-reconciliations.transition', $reconciliation), ['transition' => 'finalize'])
        ->assertSessionHasErrors('exception_reason');
    $this->patch(route('bank-reconciliations.transition', $reconciliation), [
        'transition' => 'finalize', 'reason' => 'Owner-approved timing exception supported by bank advice.',
    ])->assertSessionHasNoErrors();

    expect($reconciliation->fresh()->exception_reason)->not->toBeNull();
});

test('unauthorized users cannot view create match finalize or reopen', function () {
    $import = reconciliationStatement($this->account, $this->user, ['100.0000']);
    $reconciliation = startReconciliation($this, $import, '1000.0000');
    $unauthorized = User::factory()->create();

    $this->actingAs($unauthorized)->get(route('bank-reconciliations.index'))->assertForbidden();
    $this->post(route('bank-reconciliations.store'), [])->assertForbidden();
    $this->post(route('bank-reconciliations.matches.store', $reconciliation), [])->assertForbidden();
    $this->patch(route('bank-reconciliations.transition', $reconciliation), ['transition' => 'finalize'])->assertForbidden();
});
