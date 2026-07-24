<?php

use App\Enums\CashTransactionStatus;
use App\Enums\CashTransactionType;
use App\Enums\FundTransferStatus;
use App\Models\BusinessProfile;
use App\Models\CashTransaction;
use App\Models\DocumentSequence;
use App\Models\FinancialAccount;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\FundTransfer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function fundTransferFixture(): array
{
    $user = User::factory()->administrator()->create();
    $business = BusinessProfile::factory()->active()->create();
    $year = FiscalYear::factory()->for($business)->for($user, 'creator')->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $period = FiscalPeriod::factory()->for($year)->create(['name' => 'July 2026', 'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31', 'status' => 'open']);
    DocumentSequence::query()->create([
        'business_profile_id' => $business->id, 'fiscal_year_id' => $year->id, 'fiscal_year_scope' => $year->id,
        'document_type' => 'fund_transfer', 'prefix' => 'FT-', 'suffix' => '', 'current_number' => 0, 'padding' => 6,
        'reset_rule' => 'fiscal_year', 'active' => true, 'created_by' => $user->id, 'updated_by' => $user->id,
    ]);
    $source = FinancialAccount::factory()->create(['opening_balance' => '5000.0000', 'current_balance' => null, 'allow_transfers' => true]);
    $destination = FinancialAccount::factory()->create(['opening_balance' => '1000.0000', 'current_balance' => null, 'allow_transfers' => true]);

    return compact('user', 'period', 'source', 'destination');
}

function fundTransferData(array $fixture, array $changes = []): array
{
    return array_replace([
        'transfer_date' => '2026-07-24', 'destination_date' => '2026-07-24',
        'fiscal_period_id' => $fixture['period']->id, 'source_financial_account_id' => $fixture['source']->id,
        'destination_financial_account_id' => $fixture['destination']->id, 'amount' => '1000.0000',
        'transfer_fee' => '25.0000', 'reference_number' => 'TRF-100',
    ], $changes);
}

test('same account transfer is rejected', function () {
    $fixture = fundTransferFixture();

    $this->actingAs($fixture['user'])->post(route('fund-transfers.store'), fundTransferData($fixture, [
        'destination_financial_account_id' => $fixture['source']->id,
    ]))->assertSessionHasErrors(['source_financial_account_id', 'destination_financial_account_id']);

    expect(FundTransfer::count())->toBe(0);
});

test('same day posting atomically creates linked sides and reconciles balances with fee separated', function () {
    $fixture = fundTransferFixture();
    $this->actingAs($fixture['user'])->post(route('fund-transfers.store'), fundTransferData($fixture))->assertRedirect();
    $transfer = FundTransfer::sole();

    $this->patch(route('fund-transfers.transition', $transfer), ['status' => 'posted'])->assertSessionHasNoErrors();
    $transfer->refresh()->load('transactions');

    expect($transfer->transfer_number)->toBe('FT-000001')
        ->and($transfer->status)->toBe(FundTransferStatus::Completed)
        ->and($transfer->transactions)->toHaveCount(2)
        ->and($transfer->sourceTransaction->type)->toBe(CashTransactionType::TransferOut)
        ->and($transfer->sourceTransaction->amount)->toBe('1000.0000')
        ->and($transfer->sourceTransaction->fee_amount)->toBe('25.0000')
        ->and($transfer->destinationTransaction->type)->toBe(CashTransactionType::TransferIn)
        ->and($transfer->destinationTransaction->fee_amount)->toBe('0.0000')
        ->and($fixture['source']->fresh()->current_balance)->toBe('3975.0000')
        ->and($fixture['destination']->fresh()->current_balance)->toBe('2000.0000');
});

test('insufficient balance prevents both transactions and all balance changes', function () {
    $fixture = fundTransferFixture();
    $this->actingAs($fixture['user'])->post(route('fund-transfers.store'), fundTransferData($fixture, ['amount' => '5000.0000']));
    $transfer = FundTransfer::sole();

    $this->patch(route('fund-transfers.transition', $transfer), ['status' => 'posted'])->assertSessionHasErrors('amount');

    expect(CashTransaction::count())->toBe(0)
        ->and($transfer->fresh()->status)->toBe(FundTransferStatus::Draft)
        ->and($fixture['source']->fresh()->current_balance)->toBeNull()
        ->and($fixture['destination']->fresh()->current_balance)->toBeNull();
});

test('different dates preserve in transit state until destination completion', function () {
    $fixture = fundTransferFixture();
    $this->actingAs($fixture['user'])->post(route('fund-transfers.store'), fundTransferData($fixture, ['destination_date' => '2026-07-26']));
    $transfer = FundTransfer::sole();
    $this->patch(route('fund-transfers.transition', $transfer), ['status' => 'posted'])->assertSessionHasNoErrors();

    expect($transfer->fresh()->status)->toBe(FundTransferStatus::InTransit)
        ->and($transfer->fresh()->destinationTransaction->status)->toBe(CashTransactionStatus::Draft)
        ->and($fixture['source']->fresh()->current_balance)->toBe('3975.0000')
        ->and($fixture['destination']->fresh()->current_balance)->toBeNull();

    $this->patch(route('fund-transfers.transition', $transfer), ['status' => 'completed'])->assertSessionHasNoErrors();
    expect($transfer->fresh()->status)->toBe(FundTransferStatus::Completed)
        ->and($transfer->fresh()->destinationTransaction->status)->toBe(CashTransactionStatus::Posted)
        ->and($fixture['destination']->fresh()->current_balance)->toBe('2000.0000');
});

test('failed in transit transfer restores source and voids both linked sides', function () {
    $fixture = fundTransferFixture();
    $this->actingAs($fixture['user'])->post(route('fund-transfers.store'), fundTransferData($fixture, ['destination_date' => '2026-07-26']));
    $transfer = FundTransfer::sole();
    $this->patch(route('fund-transfers.transition', $transfer), ['status' => 'posted']);
    $this->patch(route('fund-transfers.transition', $transfer), ['status' => 'failed'])->assertSessionHasErrors('reason');
    $this->patch(route('fund-transfers.transition', $transfer), ['status' => 'failed', 'reason' => 'Bank rejected transfer'])->assertSessionHasNoErrors();

    expect($transfer->fresh()->status)->toBe(FundTransferStatus::Failed)
        ->and($transfer->fresh()->failure_reason)->toBe('Bank rejected transfer')
        ->and($fixture['source']->fresh()->current_balance)->toBe('5000.0000')
        ->and($fixture['destination']->fresh()->current_balance)->toBeNull()
        ->and(CashTransaction::query()->where('status', CashTransactionStatus::Voided)->count())->toBe(2);
});

test('voiding completed transfer safely reverses both balances once', function () {
    $fixture = fundTransferFixture();
    $this->actingAs($fixture['user'])->post(route('fund-transfers.store'), fundTransferData($fixture));
    $transfer = FundTransfer::sole();
    $this->patch(route('fund-transfers.transition', $transfer), ['status' => 'posted']);
    $this->patch(route('fund-transfers.transition', $transfer), ['status' => 'voided', 'reason' => 'Duplicate instruction'])->assertSessionHasNoErrors();

    expect($transfer->fresh()->status)->toBe(FundTransferStatus::Voided)
        ->and($fixture['source']->fresh()->current_balance)->toBe('5000.0000')
        ->and($fixture['destination']->fresh()->current_balance)->toBe('1000.0000');
    $this->patch(route('fund-transfers.transition', $transfer), ['status' => 'voided', 'reason' => 'Again'])->assertSessionHasErrors('status');
    expect($fixture['source']->fresh()->current_balance)->toBe('5000.0000');
});

test('authorization and downstream boundaries are enforced', function () {
    $fixture = fundTransferFixture();
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    $this->actingAs($viewer)->get(route('fund-transfers.index'))->assertOk();
    $this->post(route('fund-transfers.store'), fundTransferData($fixture))->assertForbidden();
    expect(Schema::hasTable('cash_transactions'))->toBeTrue()
        ->and(Schema::hasTable('journal_entries'))->toBeFalse()
        ->and(Schema::hasTable('bank_reconciliations'))->toBeTrue();
});

test('posting uses database transactions and deterministic account locks', function () {
    $source = file_get_contents(app_path('Actions/TransitionFundTransfer.php'));

    expect($source)->toContain('DB::transaction')
        ->toContain("->orderBy('id')")
        ->toContain('->lockForUpdate()')
        ->and(DB::transactionLevel())->toBe(1);
});
