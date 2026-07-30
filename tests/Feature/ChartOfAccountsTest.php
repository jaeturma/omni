<?php

use App\Enums\AccountClass;
use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Models\Account;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function validAccount(array $overrides = []): array
{
    return array_replace([
        'code' => '1015', 'name' => 'Cash Clearing',
        'account_class' => AccountClass::Asset->value, 'account_type' => AccountType::Cash->value,
        'parent_id' => null, 'is_header' => '0', 'is_postable' => '1',
        'is_control_account' => '0', 'control_account_type' => null,
        'description' => null, 'display_order' => 10,
    ], $overrides);
}

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

it('creates an account with hierarchy and a derived normal balance', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Bookkeeper');
    $parent = Account::query()->create([
        'code' => '1000', 'name' => 'Assets', 'account_class' => AccountClass::Asset,
        'account_type' => AccountType::Cash, 'normal_balance' => NormalBalance::Debit,
        'is_header' => true, 'is_postable' => false,
    ]);

    $this->actingAs($user)->post(route('accounts.store'), validAccount(['parent_id' => $parent->id]))
        ->assertRedirect(route('accounts.index'));

    $account = Account::query()->where('code', '1015')->firstOrFail();
    expect($account->parent->is($parent))->toBeTrue()
        ->and($account->normal_balance)->toBe(NormalBalance::Debit);
});

it('rejects duplicate codes, invalid header posting, mismatched classes, and circular parents', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Bookkeeper');
    $asset = Account::query()->create([
        'code' => '1000', 'name' => 'Assets', 'account_class' => AccountClass::Asset,
        'account_type' => AccountType::Cash, 'normal_balance' => NormalBalance::Debit,
        'is_header' => true, 'is_postable' => false,
    ]);
    $child = Account::query()->create([
        'code' => '1010', 'name' => 'Cash', 'account_class' => AccountClass::Asset,
        'account_type' => AccountType::Cash, 'normal_balance' => NormalBalance::Debit, 'parent_id' => $asset->id,
    ]);

    $this->actingAs($user)->post(route('accounts.store'), validAccount([
        'code' => '1010', 'is_header' => '1', 'is_postable' => '1',
        'account_class' => AccountClass::Liability->value,
    ]))->assertSessionHasErrors(['code', 'is_postable', 'account_type']);

    $this->actingAs($user)->put(route('accounts.update', $asset), validAccount([
        'code' => '1000', 'name' => 'Assets', 'parent_id' => $child->id,
        'is_header' => '1', 'is_postable' => '0',
    ]))->assertSessionHasErrors('parent_id');
});

it('prevents header posting and protects system and control accounts', function (): void {
    $account = Account::query()->create([
        'code' => '1100', 'name' => 'Accounts Receivable', 'account_class' => AccountClass::Asset,
        'account_type' => AccountType::AccountsReceivable, 'normal_balance' => NormalBalance::Debit,
        'is_control_account' => true, 'control_account_type' => 'accounts_receivable', 'is_system' => true,
    ]);
    $header = Account::query()->create([
        'code' => '1000', 'name' => 'Assets', 'account_class' => AccountClass::Asset,
        'account_type' => AccountType::Cash, 'normal_balance' => NormalBalance::Debit,
        'is_header' => true, 'is_postable' => false,
    ]);

    expect(fn () => $header->assertPostable())->toThrow(DomainException::class)
        ->and(fn () => $account->update(['account_type' => AccountType::Inventory]))->toThrow(DomainException::class)
        ->and(fn () => $account->delete())->toThrow(DomainException::class);
});

it('enforces authorization and separate activation permissions', function (): void {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $encoder = User::factory()->create();
    $encoder->assignRole('Encoder');
    $account = Account::query()->create([
        'code' => '1010', 'name' => 'Cash', 'account_class' => AccountClass::Asset,
        'account_type' => AccountType::Cash, 'normal_balance' => NormalBalance::Debit,
    ]);

    $this->actingAs($viewer)->get(route('accounts.index'))->assertSuccessful();
    $this->actingAs($viewer)->post(route('accounts.store'), validAccount())->assertForbidden();
    $this->actingAs($encoder)->patch(route('accounts.status', $account))->assertForbidden();
});

it('seeds the default chart deterministically with protected control accounts', function (): void {
    $this->seed(ChartOfAccountsSeeder::class);
    $count = Account::query()->count();
    $this->seed(ChartOfAccountsSeeder::class);

    expect(Account::query()->count())->toBe($count)
        ->and($count)->toBe(55)
        ->and(Account::query()->where('code', '1100')->value('control_account_type'))->toBe('accounts_receivable')
        ->and(Account::query()->where('code', '1000')->value('is_postable'))->toBeFalse()
        ->and(Account::query()->where('code', '1590')->value('normal_balance'))->toBe(NormalBalance::Credit)
        ->and(Account::query()->where('code', '6200')->value('account_type'))->toBe(AccountType::IncomeTaxExpense);
});
