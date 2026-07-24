<?php

use App\Enums\FinancialAccountType;
use App\Models\FinancialAccount;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function financialAccountData(array $changes = []): array
{
    return array_replace(['code' => 'main-bank', 'name' => 'Main Operating Account', 'type' => 'bank_checking',
        'account_number' => '123456789012', 'account_holder_name' => 'Omni Enterprise', 'currency' => 'php',
        'opening_balance' => '12500.2500', 'opening_balance_date' => '2026-05-01', 'allow_receipts' => '1',
        'allow_disbursements' => '1', 'allow_transfers' => '1', 'allow_reconciliation' => '1'], $changes);
}

test('authorized user creates lists views and updates every account type', function () {
    $admin = User::factory()->administrator()->create();
    foreach (FinancialAccountType::cases() as $index => $type) {
        $this->actingAs($admin)->post(route('financial-accounts.store'), financialAccountData([
            'code' => 'ACC-'.$index, 'name' => str($type->value)->replace('_', ' ')->title()->toString(), 'type' => $type->value,
        ]))->assertRedirect(route('financial-accounts.index'));
    }
    expect(FinancialAccount::count())->toBe(count(FinancialAccountType::cases()));
    $account = FinancialAccount::query()->firstOrFail();
    $this->get(route('financial-accounts.index'))->assertOk()->assertSee($account->name);
    $this->put(route('financial-accounts.update', $account), financialAccountData(['code' => $account->code, 'name' => 'Updated Account', 'account_number' => '']))->assertRedirect();
    expect($account->fresh()->name)->toBe('Updated Account')->and($account->fresh()->account_number)->toBe('123456789012');
});

test('validation prevents duplicate codes invalid amounts and missing opening dates', function () {
    $admin = User::factory()->administrator()->create();
    FinancialAccount::factory()->create(['code' => 'MAIN-BANK']);
    $this->actingAs($admin)->post(route('financial-accounts.store'), financialAccountData())->assertSessionHasErrors('code');
    $this->post(route('financial-accounts.store'), financialAccountData(['code' => 'BAD CODE', 'type' => 'crypto', 'currency' => 'PESO', 'opening_balance' => '1.12345', 'opening_balance_date' => null]))
        ->assertSessionHasErrors(['code', 'type', 'currency', 'opening_balance', 'opening_balance_date']);
});

test('account numbers are encrypted at rest and masked without sensitive permission', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin)->post(route('financial-accounts.store'), financialAccountData());
    $account = FinancialAccount::sole();
    expect(DB::table('financial_accounts')->value('account_number'))->not->toBe('123456789012')
        ->and($account->maskedAccountNumber())->toBe('•••• 9012');
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('financial-accounts.show', $account))->assertOk()->assertSee('•••• 9012')->assertDontSee('123456789012');
    $this->actingAs($admin)->get(route('financial-accounts.show', $account))->assertOk()->assertSee('123456789012');
});

test('opening balance and activation changes retain audit attribution', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin)->post(route('financial-accounts.store'), financialAccountData());
    $account = FinancialAccount::sole();
    expect($account->opening_balance)->toBe('12500.2500')->and($account->opening_balance_date->toDateString())->toBe('2026-05-01')
        ->and($account->opening_balance_set_by)->toBe($admin->id)->and($account->opening_balance_set_at)->not->toBeNull();
    $this->patch(route('financial-accounts.status', $account), ['active' => false])->assertSessionHasErrors('reason');
    $this->patch(route('financial-accounts.status', $account), ['active' => false, 'reason' => 'Account closed by bank'])->assertRedirect();
    expect($account->fresh()->active)->toBeFalse()->and($account->fresh()->deactivated_by)->toBe($admin->id)
        ->and($account->fresh()->deactivation_reason)->toBe('Account closed by bank');
    $this->put(route('financial-accounts.update', $account), financialAccountData())->assertForbidden();
    $this->patch(route('financial-accounts.status', $account), ['active' => true])->assertRedirect();
    expect($account->fresh()->active)->toBeTrue()->and($account->fresh()->activated_by)->toBe($admin->id);
});

test('authorization is enforced without secrets or future operational tables', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $admin = User::factory()->administrator()->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($viewer)->get(route('financial-accounts.index'))->assertOk();
    $this->post(route('financial-accounts.store'), financialAccountData())->assertForbidden();
    $this->patch(route('financial-accounts.status', $account), ['active' => false, 'reason' => 'Denied'])->assertForbidden();
    foreach (['password', 'pin', 'api_key', 'api_secret', 'online_banking_username'] as $column) {
        expect(Schema::hasColumn('financial_accounts', $column))->toBeFalse();
    }
    expect(Schema::hasTable('cash_transactions'))->toBeTrue()
        ->and(Schema::hasTable('fund_transfers'))->toBeTrue()
        ->and(Schema::hasTable('bank_statement_imports'))->toBeTrue()
        ->and(Schema::hasTable('bank_statement_lines'))->toBeTrue()
        ->and(Schema::hasTable('bank_reconciliations'))->toBeTrue();
    foreach (['journal_entries'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
});
