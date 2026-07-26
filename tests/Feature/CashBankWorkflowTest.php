<?php

use App\Enums\CashTransactionStatus;
use App\Enums\CashTransactionType;
use App\Enums\FinancialAccountType;
use App\Enums\ReconciliationState;
use App\Models\DocumentSequence;
use App\Models\JournalEntry;
use App\Support\CashBankWorkflow;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

test('cash transaction statuses enforce immutable posting and controlled voiding', function () {
    expect(CashTransactionStatus::Draft->canTransitionTo(CashTransactionStatus::Posted))->toBeTrue()
        ->and(CashTransactionStatus::Draft->canTransitionTo(CashTransactionStatus::Voided))->toBeFalse()
        ->and(CashTransactionStatus::Posted->canTransitionTo(CashTransactionStatus::Draft))->toBeFalse()
        ->and(CashTransactionStatus::Posted->canTransitionTo(CashTransactionStatus::Voided))->toBeTrue()
        ->and(CashTransactionStatus::Voided->allowedTransitions())->toBeEmpty();
});

test('account transaction and reconciliation types are explicit and complete', function () {
    expect(array_column(FinancialAccountType::cases(), 'value'))->toBe([
        'cash_on_hand', 'petty_cash', 'bank_checking', 'bank_savings', 'e_wallet', 'clearing_account', 'other_cash_equivalent',
    ])->and(array_column(CashTransactionType::cases(), 'value'))->toBe([
        'customer_receipt', 'supplier_payment', 'expense_payment', 'deposit', 'withdrawal', 'transfer_in', 'transfer_out',
        'petty_cash_release', 'petty_cash_return', 'petty_cash_replenishment', 'adjustment', 'opening_balance',
    ])->and(array_column(ReconciliationState::cases(), 'value'))->toBe(['unreconciled', 'matched', 'reconciled', 'disputed']);
});

test('transaction categories distinguish linked manual transfer and petty cash workflows', function () {
    $categorized = collect([
        ...CashBankWorkflow::SOURCE_LINKED_TYPES, ...CashBankWorkflow::MANUAL_TYPES,
        ...CashBankWorkflow::TRANSFER_TYPES, ...CashBankWorkflow::PETTY_CASH_TYPES,
    ]);

    expect($categorized->unique())->toHaveCount(count(CashTransactionType::cases()))
        ->and(CashBankWorkflow::TRANSFER_TYPES)->toBe([CashTransactionType::TransferOut, CashTransactionType::TransferIn])
        ->and(CashBankWorkflow::SOURCE_LINKED_TYPES)->not->toContain(CashTransactionType::Adjustment);
});

test('phase five permissions are seeded by role', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::query()->whereIn('name', CashBankWorkflow::PERMISSIONS)->count())->toBe(count(CashBankWorkflow::PERMISSIONS))
        ->and(Role::findByName('Administrator')->hasAllPermissions(CashBankWorkflow::PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Owner')->hasAllPermissions(CashBankWorkflow::PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Bookkeeper')->hasAllPermissions(CashBankWorkflow::PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Encoder')->hasAllPermissions(CashBankWorkflow::ENCODER_PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Viewer')->hasAllPermissions(CashBankWorkflow::VIEW_PERMISSIONS))->toBeTrue()
        ->and(Role::findByName('Viewer')->hasPermissionTo('cash-receipts.create'))->toBeFalse();
});

test('cash documents map to controlled sequence codes while future operational records remain absent', function () {
    foreach (CashBankWorkflow::DOCUMENT_SEQUENCES as $documentType) {
        expect(DocumentSequence::TYPES)->toContain($documentType);
    }

    expect(Schema::hasTable('cash_transactions'))->toBeTrue()
        ->and(Schema::hasTable('fund_transfers'))->toBeTrue()
        ->and(Schema::hasTable('petty_cash_funds'))->toBeTrue()
        ->and(Schema::hasTable('petty_cash_vouchers'))->toBeTrue();

    expect(Schema::hasTable('bank_statement_imports'))->toBeTrue()
        ->and(Schema::hasTable('bank_statement_lines'))->toBeTrue()
        ->and(Schema::hasTable('bank_reconciliations'))->toBeTrue()
        ->and(Schema::hasTable('bank_reconciliation_matches'))->toBeTrue();

    expect(JournalEntry::query()->count())->toBe(0);
});
