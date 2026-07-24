<?php

use App\Models\BankStatementImport;
use App\Models\CashTransaction;
use App\Models\FinancialAccount;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('Bookkeeper');
    $this->account = FinancialAccount::factory()->create(['allow_reconciliation' => true]);
});

function statementPayload(FinancialAccount $account, string $csv): array
{
    return [
        'financial_account_id' => $account->id,
        'statement_start_date' => '2026-07-01',
        'statement_end_date' => '2026-07-31',
        'statement_file' => UploadedFile::fake()->createWithContent('july.csv', $csv),
        'date_format' => 'Y-m-d',
        'transaction_date_column' => 'Txn Date',
        'posting_date_column' => 'Posted',
        'description_column' => 'Memo',
        'reference_number_column' => 'Ref',
        'debit_column' => 'Money Out',
        'credit_column' => 'Money In',
        'running_balance_column' => 'Balance',
    ];
}

test('valid csv imports mapped immutable staging lines without changing cash transactions', function () {
    $cashCount = CashTransaction::query()->count();
    $csv = "Txn Date,Posted,Memo,Ref,Money Out,Money In,Balance\n2026-07-03,2026-07-04,Customer deposit,DEP-1,,1500.25,9500.25\n2026-07-05,2026-07-05,Bank fee,FEE-1,25.50,,9474.75\n";

    $response = $this->actingAs($this->user)->post(route('bank-statements.store'), statementPayload($this->account, $csv));

    $import = BankStatementImport::query()->firstOrFail();
    $response->assertRedirect(route('bank-statements.show', $import));
    expect($import->lines)->toHaveCount(2)
        ->and($import->lines[0]->normalized_amount)->toBe('1500.2500')
        ->and($import->lines[1]->normalized_amount)->toBe('-25.5000')
        ->and($import->lines[0]->original_values['Money In'])->toBe('1500.25')
        ->and(CashTransaction::query()->count())->toBe($cashCount);
});

test('invalid csv row rejects the entire import', function () {
    $csv = "Txn Date,Posted,Memo,Ref,Money Out,Money In,Balance\nnot-a-date,2026-07-04,Deposit,DEP-1,,100,100\n";

    $this->actingAs($this->user)->from(route('bank-statements.create'))
        ->post(route('bank-statements.store'), statementPayload($this->account, $csv))
        ->assertRedirect(route('bank-statements.create'))->assertSessionHasErrors('statement_file');

    expect(BankStatementImport::query()->count())->toBe(0);
});

test('duplicate file hash is blocked per financial account', function () {
    $csv = "Txn Date,Posted,Memo,Ref,Money Out,Money In,Balance\n2026-07-03,2026-07-03,Deposit,R1,,100,100\n";
    $this->actingAs($this->user)->post(route('bank-statements.store'), statementPayload($this->account, $csv));

    $this->actingAs($this->user)->post(route('bank-statements.store'), statementPayload($this->account, $csv))
        ->assertSessionHasErrors('statement_file');

    expect(BankStatementImport::query()->count())->toBe(1);
});

test('column mapping and transaction date fallback normalize a valid row', function () {
    $payload = statementPayload($this->account, "Txn Date,Memo,Money Out,Money In\n2026-07-10,Withdrawal,50.125,\n");
    $payload['posting_date_column'] = null;
    $payload['reference_number_column'] = null;
    $payload['running_balance_column'] = null;

    $this->actingAs($this->user)->post(route('bank-statements.store'), $payload)->assertSessionHasNoErrors();

    $line = BankStatementImport::query()->firstOrFail()->lines()->firstOrFail();
    expect($line->posting_date->toDateString())->toBe('2026-07-10')
        ->and($line->normalized_amount)->toBe('-50.1250');
});

test('unfinalized import can be rolled back while preserving its batch audit', function () {
    $csv = "Txn Date,Posted,Memo,Ref,Money Out,Money In,Balance\n2026-07-03,2026-07-03,Deposit,R1,,100,100\n";
    $this->actingAs($this->user)->post(route('bank-statements.store'), statementPayload($this->account, $csv));
    $import = BankStatementImport::query()->firstOrFail();

    $this->actingAs($this->user)->delete(route('bank-statements.destroy', $import))->assertRedirect(route('bank-statements.index'));

    expect($import->fresh()->rolled_back_at)->not->toBeNull()
        ->and($import->lines()->count())->toBe(0);
});

test('finalized import cannot be rolled back', function () {
    $csv = "Txn Date,Posted,Memo,Ref,Money Out,Money In,Balance\n2026-07-03,2026-07-03,Deposit,R1,,100,100\n";
    $this->actingAs($this->user)->post(route('bank-statements.store'), statementPayload($this->account, $csv));
    $import = BankStatementImport::query()->firstOrFail();
    $import->update(['finalized_at' => now(), 'finalized_by' => $this->user->id]);

    $this->actingAs($this->user)->delete(route('bank-statements.destroy', $import))->assertForbidden();
    expect($import->lines()->count())->toBe(1);
});

test('users without bank statement permissions are denied', function () {
    $unauthorized = User::factory()->create();
    $this->actingAs($unauthorized)->get(route('bank-statements.index'))->assertForbidden();
    $this->actingAs($unauthorized)->post(route('bank-statements.store'), [])->assertForbidden();
});
