<?php

use App\Enums\AccountClass;
use App\Enums\AccountingSourceType;
use App\Enums\AccountType;
use App\Enums\JournalEntryStatus;
use App\Enums\JournalEntryType;
use App\Enums\NormalBalance;
use App\Models\FiscalPeriod;
use App\Support\AccountingWorkflow;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

test('account classes types and normal balances are explicit', function () {
    expect(array_column(AccountClass::cases(), 'value'))->toBe([
        'asset', 'liability', 'owner_equity', 'income', 'cost_of_sales', 'expense', 'other_income', 'other_expense',
    ])->and(AccountClass::Asset->normalBalance())->toBe(NormalBalance::Debit)
        ->and(AccountClass::Liability->normalBalance())->toBe(NormalBalance::Credit)
        ->and(AccountType::Inventory->accountClass())->toBe(AccountClass::Asset)
        ->and(AccountType::SalesIncome->normalBalance())->toBe(NormalBalance::Credit)
        ->and(AccountType::CostOfSales->normalBalance())->toBe(NormalBalance::Debit)
        ->and(AccountType::OwnerDrawings->accountClass())->toBe(AccountClass::OwnerEquity)
        ->and(AccountType::OwnerDrawings->normalBalance())->toBe(NormalBalance::Debit)
        ->and(AccountingWorkflow::RETAINED_EARNINGS_TYPE)->toBe(AccountType::RetainedEarnings);
});

test('journal and source types and status transitions are explicit', function () {
    expect(array_column(JournalEntryType::cases(), 'value'))->toBe([
        'opening', 'sales', 'collection', 'purchase', 'supplier_payment', 'expense', 'cash_receipt',
        'cash_disbursement', 'transfer', 'inventory', 'adjustment', 'reversal', 'closing',
    ])->and(array_column(AccountingSourceType::cases(), 'value'))->toBe([
        'sales_invoice', 'customer_payment', 'supplier_invoice', 'supplier_payment', 'expense',
        'cash_receipt', 'cash_disbursement', 'fund_transfer', 'inventory_movement', 'manual',
    ])->and(JournalEntryStatus::Draft->canTransitionTo(JournalEntryStatus::Posted))->toBeTrue()
        ->and(JournalEntryStatus::Posted->canTransitionTo(JournalEntryStatus::Draft))->toBeFalse()
        ->and(JournalEntryStatus::Posted->canTransitionTo(JournalEntryStatus::Reversed))->toBeTrue()
        ->and(JournalEntryStatus::Reversed->allowedTransitions())->toBeEmpty()
        ->and(AccountingWorkflow::SOURCE_POSTING_UNIQUE)->toBeTrue();
});

test('balancing is decimal safe and exact at stored precision', function () {
    expect(AccountingWorkflow::AMOUNT_SCALE)->toBe(4)
        ->and(AccountingWorkflow::BALANCING_TOLERANCE)->toBe('0.0000')
        ->and(AccountingWorkflow::ROUNDING_METHOD)->toBe('round_half_up')
        ->and(AccountingWorkflow::isBalanced('1000000000000.1234', '1000000000000.1234'))->toBeTrue()
        ->and(AccountingWorkflow::isBalanced('100.0000', '100.0001'))->toBeFalse()
        ->and(AccountingWorkflow::isBalanced('100.0001', '100.0000'))->toBeFalse();
});

test('posting dates require the selected open fiscal period', function () {
    $period = FiscalPeriod::factory()->make([
        'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31', 'status' => 'open',
    ]);
    AccountingWorkflow::assertPostingPeriod($period, '2026-07-15');

    expect(fn () => AccountingWorkflow::assertPostingPeriod($period, '2026-08-01'))
        ->toThrow(DomainException::class, 'outside the selected fiscal period');

    $period->status = 'closed';
    expect(fn () => AccountingWorkflow::assertPostingPeriod($period, '2026-07-15'))
        ->toThrow(DomainException::class, 'open fiscal period');

    $period->status = 'locked';
    expect(fn () => AccountingWorkflow::assertPostingPeriod($period, '2026-07-15'))
        ->toThrow(DomainException::class, 'open fiscal period');
});

test('phase seven permissions are seeded by role', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::query()->whereIn('name', AccountingWorkflow::PERMISSIONS)->count())->toBe(count(AccountingWorkflow::PERMISSIONS))
        ->and(Role::findByName('Administrator')->hasAllPermissions(AccountingWorkflow::PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Owner')->hasAllPermissions(AccountingWorkflow::PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Bookkeeper')->hasAllPermissions(AccountingWorkflow::PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Encoder')->hasAllPermissions(AccountingWorkflow::ENCODER_PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Viewer')->hasAllPermissions(AccountingWorkflow::VIEW_PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Viewer')->hasPermissionTo('journals.post'))->toBeFalse();
});

test('accounting foundations create no ledger or reporting records', function () {
    expect(Schema::hasTable('accounts'))->toBeTrue();
    expect(Schema::hasTable('journal_entries'))->toBeTrue();
    expect(Schema::hasTable('journal_entry_lines'))->toBeTrue();
    expect(Schema::hasTable('posting_rules'))->toBeTrue();

    foreach (['chart_of_accounts', 'general_ledgers', 'trial_balances', 'financial_statements', 'tax_returns'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
});
