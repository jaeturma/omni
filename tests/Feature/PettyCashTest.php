<?php

use App\Enums\CashTransactionStatus;
use App\Enums\CashTransactionType;
use App\Enums\FinancialAccountType;
use App\Enums\PettyCashVoucherStatus;
use App\Models\BusinessProfile;
use App\Models\CashTransaction;
use App\Models\DocumentSequence;
use App\Models\FinancialAccount;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PettyCashFund;
use App\Models\PettyCashReplenishment;
use App\Models\PettyCashVoucher;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function pettyCashFixture(): array
{
    $user = User::factory()->administrator()->create();
    $business = BusinessProfile::factory()->active()->create();
    $year = FiscalYear::factory()->for($business)->for($user, 'creator')->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $period = FiscalPeriod::factory()->for($year)->create(['name' => 'July 2026', 'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31', 'status' => 'open']);
    DocumentSequence::query()->create([
        'business_profile_id' => $business->id, 'fiscal_year_id' => $year->id, 'fiscal_year_scope' => $year->id,
        'document_type' => 'petty_cash_voucher', 'prefix' => 'PCV-', 'suffix' => '', 'current_number' => 0, 'padding' => 6,
        'reset_rule' => 'fiscal_year', 'active' => true, 'created_by' => $user->id, 'updated_by' => $user->id,
    ]);
    $account = FinancialAccount::factory()->create([
        'type' => FinancialAccountType::PettyCash, 'opening_balance' => '1000.0000',
        'current_balance' => null, 'allow_transfers' => true,
    ]);

    return compact('user', 'period', 'account');
}

function createPettyCashFund(array $fixture): PettyCashFund
{
    actingAs($fixture['user']);
    post(route('petty-cash.funds.store'), [
        'financial_account_id' => $fixture['account']->id,
        'custodian_id' => $fixture['user']->id,
        'approved_fund_limit' => '1000.0000',
    ])->assertSessionHasNoErrors();

    return PettyCashFund::sole();
}

function pettyCashVoucherData(array $fixture, PettyCashFund $fund, array $changes = []): array
{
    return array_replace([
        'petty_cash_fund_id' => $fund->id, 'voucher_date' => '2026-07-25',
        'fiscal_period_id' => $fixture['period']->id, 'payee' => 'Office Runner',
        'expense_category' => 'transportation', 'purpose' => 'Local document delivery',
        'amount_released' => '200.0000',
    ], $changes);
}

function createAndReleasePettyCashVoucher(array $fixture, PettyCashFund $fund, array $changes = []): PettyCashVoucher
{
    post(route('petty-cash.vouchers.store'), pettyCashVoucherData($fixture, $fund, $changes))->assertSessionHasNoErrors();
    $voucher = PettyCashVoucher::query()->latest('id')->firstOrFail();
    patch(route('petty-cash.vouchers.transition', $voucher), ['status' => 'released'])->assertSessionHasNoErrors();

    return $voucher->fresh();
}

test('fund creation requires a dedicated petty cash account and initializes its operational balance', function () {
    $fixture = pettyCashFixture();
    $fund = createPettyCashFund($fixture);

    expect($fund->financialAccount->is($fixture['account']))->toBeTrue()
        ->and($fund->custodian->is($fixture['user']))->toBeTrue()
        ->and($fund->current_operational_balance)->toBe('1000.0000')
        ->and($fixture['account']->fresh()->allow_receipts)->toBeFalse()
        ->and($fixture['account']->fresh()->allow_disbursements)->toBeFalse()
        ->and($fixture['account']->fresh()->allow_transfers)->toBeFalse();

    $ordinaryAccount = FinancialAccount::factory()->create(['type' => FinancialAccountType::BankChecking]);
    $this->post(route('petty-cash.funds.store'), [
        'financial_account_id' => $ordinaryAccount->id, 'custodian_id' => $fixture['user']->id, 'approved_fund_limit' => '1000.0000',
    ])->assertSessionHasErrors('financial_account_id');
});

test('release uses controlled numbering and prevents amounts above available balance', function () {
    $fixture = pettyCashFixture();
    $fund = createPettyCashFund($fixture);
    $voucher = createAndReleasePettyCashVoucher($fixture, $fund);

    expect($voucher->voucher_number)->toBe('PCV-000001')
        ->and($voucher->status)->toBe(PettyCashVoucherStatus::Released)
        ->and($fund->fresh()->current_operational_balance)->toBe('800.0000')
        ->and($fixture['account']->fresh()->current_balance)->toBe('800.0000')
        ->and($voucher->transactions()->sole()->type)->toBe(CashTransactionType::PettyCashRelease);

    $this->post(route('petty-cash.vouchers.store'), pettyCashVoucherData($fixture, $fund, ['amount_released' => '900.0000']));
    $second = PettyCashVoucher::query()->latest('id')->firstOrFail();
    $this->patch(route('petty-cash.vouchers.transition', $second), ['status' => 'released'])->assertSessionHasErrors('amount_released');
    expect($second->fresh()->status)->toBe(PettyCashVoucherStatus::Draft);
});

test('full liquidation reconciles the release without a cash return', function () {
    $fixture = pettyCashFixture();
    $fund = createPettyCashFund($fixture);
    $voucher = createAndReleasePettyCashVoucher($fixture, $fund);
    $this->patch(route('petty-cash.vouchers.transition', $voucher), [
        'status' => 'liquidated', 'amount_liquidated' => '200.0000', 'amount_returned' => '0.0000', 'receipt_available' => true,
    ])->assertSessionHasNoErrors();

    expect($voucher->fresh()->status)->toBe(PettyCashVoucherStatus::Liquidated)
        ->and($voucher->fresh()->receipt_available)->toBeTrue()
        ->and($fund->fresh()->current_operational_balance)->toBe('800.0000')
        ->and($voucher->fresh()->transactions)->toHaveCount(1);
});

test('partial liquidation tracks returned cash and missing receipt', function () {
    $fixture = pettyCashFixture();
    $fund = createPettyCashFund($fixture);
    $voucher = createAndReleasePettyCashVoucher($fixture, $fund);
    $this->patch(route('petty-cash.vouchers.transition', $voucher), [
        'status' => 'liquidated', 'amount_liquidated' => '150.0000', 'amount_returned' => '50.0000', 'receipt_available' => false,
    ])->assertSessionHasNoErrors();

    expect($voucher->fresh()->amount_returned)->toBe('50.0000')
        ->and($voucher->fresh()->receipt_available)->toBeFalse()
        ->and($fund->fresh()->current_operational_balance)->toBe('850.0000')
        ->and($fixture['account']->fresh()->current_balance)->toBe('850.0000')
        ->and($voucher->fresh()->transactions()->where('type', CashTransactionType::PettyCashReturn)->exists())->toBeTrue();
});

test('liquidation requires released cash to be fully accounted for', function () {
    $fixture = pettyCashFixture();
    $fund = createPettyCashFund($fixture);
    $voucher = createAndReleasePettyCashVoucher($fixture, $fund);

    $this->patch(route('petty-cash.vouchers.transition', $voucher), [
        'status' => 'liquidated', 'amount_liquidated' => '150.0000', 'amount_returned' => '25.0000', 'receipt_available' => true,
    ])->assertSessionHasErrors('amount_liquidated');
    expect($voucher->fresh()->status)->toBe(PettyCashVoucherStatus::Released);
});

test('replenishment restores liquidated expenditure and prevents duplicate voucher use', function () {
    $fixture = pettyCashFixture();
    $fund = createPettyCashFund($fixture);
    $voucher = createAndReleasePettyCashVoucher($fixture, $fund);
    $this->patch(route('petty-cash.vouchers.transition', $voucher), [
        'status' => 'liquidated', 'amount_liquidated' => '150.0000', 'amount_returned' => '50.0000', 'receipt_available' => true,
    ]);
    $source = FinancialAccount::factory()->create(['opening_balance' => '5000.0000', 'current_balance' => null, 'allow_transfers' => true]);
    $data = [
        'petty_cash_fund_id' => $fund->id, 'source_financial_account_id' => $source->id,
        'replenishment_date' => '2026-07-25', 'fiscal_period_id' => $fixture['period']->id,
        'reference_number' => 'REP-100', 'voucher_ids' => [$voucher->id],
    ];

    $this->post(route('petty-cash.replenishments.store'), $data)->assertSessionHasNoErrors();
    $replenishment = PettyCashReplenishment::sole();
    expect($replenishment->amount)->toBe('150.0000')
        ->and($replenishment->vouchers()->sole()->is($voucher))->toBeTrue()
        ->and($fund->fresh()->current_operational_balance)->toBe('1000.0000')
        ->and($fixture['account']->fresh()->current_balance)->toBe('1000.0000')
        ->and($source->fresh()->current_balance)->toBe('4850.0000')
        ->and($replenishment->transactions)->toHaveCount(2);

    $this->post(route('petty-cash.replenishments.store'), $data)->assertSessionHasErrors('voucher_ids');
    expect(PettyCashReplenishment::count())->toBe(1);
});

test('voiding an unreplenished liquidated voucher restores net expenditure once', function () {
    $fixture = pettyCashFixture();
    $fund = createPettyCashFund($fixture);
    $voucher = createAndReleasePettyCashVoucher($fixture, $fund);
    $this->patch(route('petty-cash.vouchers.transition', $voucher), [
        'status' => 'liquidated', 'amount_liquidated' => '150.0000', 'amount_returned' => '50.0000', 'receipt_available' => false,
    ]);
    $this->patch(route('petty-cash.vouchers.transition', $voucher), ['status' => 'voided'])->assertSessionHasErrors('reason');
    $this->patch(route('petty-cash.vouchers.transition', $voucher), ['status' => 'voided', 'reason' => 'Cancelled expenditure'])->assertSessionHasNoErrors();

    expect($fund->fresh()->current_operational_balance)->toBe('1000.0000')
        ->and($fixture['account']->fresh()->current_balance)->toBe('1000.0000')
        ->and(CashTransaction::query()->where('status', CashTransactionStatus::Voided)->count())->toBe(2);
});

test('authorization and downstream boundaries are enforced', function () {
    $fixture = pettyCashFixture();
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    $this->actingAs($viewer)->get(route('petty-cash.index'))->assertOk();
    $this->post(route('petty-cash.funds.store'), [
        'financial_account_id' => $fixture['account']->id, 'custodian_id' => $viewer->id, 'approved_fund_limit' => '1000.0000',
    ])->assertForbidden();
    expect(Schema::hasTable('petty_cash_funds'))->toBeTrue()
        ->and(JournalEntry::query()->count())->toBe(0)
        ->and(Schema::hasTable('bank_reconciliations'))->toBeTrue();
});
