<?php

use App\Actions\SavePostingRule;
use App\Enums\PostingSourceType;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\PostingRule;
use App\Models\User;
use App\Services\ResolvePostingRule;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

function postingRuleContext(): array
{
    test()->seed([RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);
    $user = User::factory()->create();
    $user->assignRole('Bookkeeper');
    $accounts = Account::query()->whereIn('code', ['1010', '3010'])->orderBy('code')->get();

    return compact('user', 'accounts');
}

function postingRuleData(array $context, array $overrides = []): array
{
    return array_replace([
        'name' => 'Sales fallback',
        'source_type' => PostingSourceType::Sale->value,
        'debit_account_id' => $context['accounts'][0]->id,
        'credit_account_id' => $context['accounts'][1]->id,
        'product_category_id' => null,
        'service_category_id' => null,
        'expense_category' => null,
        'customer_type' => null,
        'supplier_type' => null,
        'financial_account_id' => null,
        'tax_code' => null,
        'warehouse_id' => null,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
    ], $overrides);
}

it('resolves the most specific mapping before an explicit fallback', function (): void {
    $context = postingRuleContext();
    $save = app(SavePostingRule::class);
    $fallback = $save->handle(postingRuleData($context), $context['user']->id);
    $specific = $save->handle(postingRuleData($context, [
        'name' => 'Government sales',
        'customer_type' => 'government',
        'debit_account_id' => $context['accounts'][1]->id,
        'credit_account_id' => $context['accounts'][0]->id,
    ]), $context['user']->id);
    $resolver = app(ResolvePostingRule::class);

    expect($resolver->resolve(PostingSourceType::Sale, '2026-07-15', ['customer_type' => 'government'])->is($specific))->toBeTrue()
        ->and($resolver->resolve(PostingSourceType::Sale, '2026-07-15', ['customer_type' => 'private'])->is($fallback))->toBeTrue();
});

it('blocks overlapping mappings that could resolve ambiguously', function (): void {
    $context = postingRuleContext();
    $save = app(SavePostingRule::class);
    $save->handle(postingRuleData($context, ['customer_type' => 'government']), $context['user']->id);

    expect(fn () => $save->handle(postingRuleData($context, [
        'name' => 'Overlapping tax mapping',
        'tax_code' => 'none',
    ]), $context['user']->id))->toThrow(ValidationException::class);
});

it('honors effective dates and rejects inactive mapped accounts', function (): void {
    $context = postingRuleContext();
    $save = app(SavePostingRule::class);
    $first = $save->handle(postingRuleData($context, ['effective_to' => '2026-06-30']), $context['user']->id);
    $second = $save->handle(postingRuleData($context, [
        'name' => 'New sales fallback',
        'effective_from' => '2026-07-01',
        'debit_account_id' => $context['accounts'][1]->id,
        'credit_account_id' => $context['accounts'][0]->id,
    ]), $context['user']->id);
    $resolver = app(ResolvePostingRule::class);

    expect($resolver->resolve(PostingSourceType::Sale, '2026-06-30')->is($first))->toBeTrue()
        ->and($resolver->resolve(PostingSourceType::Sale, '2026-07-01')->is($second))->toBeTrue();

    $context['accounts'][0]->update(['is_active' => false]);
    $this->actingAs($context['user'])->post(route('posting-rules.store'), postingRuleData($context, [
        'name' => 'Inactive mapping',
        'source_type' => PostingSourceType::Purchase->value,
    ]))->assertSessionHasErrors('debit_account_id');
});

it('previews balanced decimal-safe lines without creating a journal', function (): void {
    $context = postingRuleContext();
    app(SavePostingRule::class)->handle(postingRuleData($context), $context['user']->id);
    $before = JournalEntry::query()->count();

    $this->actingAs($context['user'])->post(route('posting-rules.preview'), [
        'source_type' => PostingSourceType::Sale->value,
        'posting_date' => '2026-07-15',
        'amount' => '1234.5678',
    ])->assertSuccessful()->assertSee('1,234.5678');

    expect(JournalEntry::query()->count())->toBe($before);
});

it('enforces posting rule authorization and audits changes', function (): void {
    $context = postingRuleContext();
    $this->actingAs($context['user'])->post(route('posting-rules.store'), postingRuleData($context))->assertRedirect(route('posting-rules.index'));
    $rule = PostingRule::query()->sole();
    expect($rule->created_by)->toBe($context['user']->id)->and($rule->updated_by)->toBe($context['user']->id);

    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('posting-rules.index'))->assertSuccessful();
    $this->actingAs($viewer)->post(route('posting-rules.store'), postingRuleData($context))->assertForbidden();
    $this->actingAs($viewer)->post(route('posting-rules.preview'), [
        'source_type' => PostingSourceType::Sale->value,
        'posting_date' => '2026-07-15',
        'amount' => '1.0000',
    ])->assertForbidden();
});
