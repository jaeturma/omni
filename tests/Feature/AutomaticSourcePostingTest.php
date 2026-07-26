<?php

use App\Actions\SavePostingRule;
use App\Enums\PostingSourceType;
use App\Models\Account;
use App\Models\CustomerPayment;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PostingRule;
use App\Models\SalesInvoice;
use App\Models\SourcePosting;
use App\Models\User;
use App\Services\AutomaticSourcePosting;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function automaticPostingContext(): array
{
    test()->seed([RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);
    $user = User::factory()->create();
    $user->assignRole('Bookkeeper');
    $year = FiscalYear::factory()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $period = FiscalPeriod::factory()->create([
        'fiscal_year_id' => $year->id,
        'starts_on' => '2026-07-01',
        'ends_on' => '2026-07-31',
        'status' => 'open',
    ]);
    $accounts = Account::query()->whereIn('code', ['1010', '3010', '4020'])->orderBy('code')->get();

    return compact('user', 'period', 'accounts');
}

function createAutomaticRule(array $context, PostingSourceType $sourceType, int $debitIndex = 0, int $creditIndex = 1): PostingRule
{
    return app(SavePostingRule::class)->handle([
        'name' => str($sourceType->value)->headline()->append(' fallback')->toString(),
        'source_type' => $sourceType->value,
        'debit_account_id' => $context['accounts'][$debitIndex]->id,
        'credit_account_id' => $context['accounts'][$creditIndex]->id,
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
    ], $context['user']->id);
}

function automaticSalesInvoice(array $context): SalesInvoice
{
    return SalesInvoice::factory()->create([
        'fiscal_period_id' => $context['period']->id,
        'invoice_number' => 'SI-2026-0001',
        'invoice_date' => '2026-07-15',
        'gross_amount' => '1000.0000',
        'discount_amount' => '100.0000',
        'net_sales_amount' => '900.0000',
        'total_receivable' => '900.0000',
        'balance_due' => '900.0000',
        'status' => 'draft',
        'created_by' => $context['user']->id,
        'updated_by' => $context['user']->id,
    ]);
}

it('automatically creates one balanced linked journal and preserves gross sales and discounts', function (): void {
    $context = automaticPostingContext();
    createAutomaticRule($context, PostingSourceType::Sale);
    createAutomaticRule($context, PostingSourceType::SalesDiscount, 2, 0);
    $invoice = automaticSalesInvoice($context);

    $invoice->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $context['user']->id]);

    $posting = SourcePosting::query()->sole();
    $journal = JournalEntry::query()->with('lines')->sole();
    expect($posting->status)->toBe('posted')
        ->and($posting->journal_entry_id)->toBe($journal->id)
        ->and($journal->total_debit)->toBe('1000.0000')
        ->and($journal->total_credit)->toBe('1000.0000')
        ->and($journal->lines)->toHaveCount(3)
        ->and($journal->lines->pluck('description')->all())->toContain('Gross sales', 'Sales discounts');

    app(AutomaticSourcePosting::class)->attempt($invoice, $context['user']->id);
    expect(JournalEntry::query()->count())->toBe(1)
        ->and($posting->fresh()->attempt_count)->toBe(1);

    $posting->update(['journal_entry_id' => null, 'status' => 'failed']);
    $this->actingAs($context['user'])->post(route('source-postings.rebuild-link', $posting))->assertRedirect();
    expect($posting->fresh()->journal_entry_id)->toBe($journal->id)
        ->and($posting->fresh()->status)->toBe('posted');
});

it('records missing mappings without a partial journal and retries after correction', function (): void {
    $context = automaticPostingContext();
    $invoice = automaticSalesInvoice($context);
    $invoice->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $context['user']->id]);

    $posting = SourcePosting::query()->sole();
    expect($posting->status)->toBe('failed')
        ->and($posting->failure_reason)->toContain('No effective posting rule')
        ->and(JournalEntry::query()->count())->toBe(0);

    createAutomaticRule($context, PostingSourceType::Sale);
    createAutomaticRule($context, PostingSourceType::SalesDiscount, 2, 0);
    $this->actingAs($context['user'])->post(route('source-postings.retry', $posting))->assertRedirect();

    expect($posting->fresh()->status)->toBe('posted')
        ->and($posting->fresh()->attempt_count)->toBe(2)
        ->and(JournalEntry::query()->count())->toBe(1);
});

it('keeps customer cash and withholding components separate', function (): void {
    $context = automaticPostingContext();
    createAutomaticRule($context, PostingSourceType::CustomerCollection);
    createAutomaticRule($context, PostingSourceType::CustomerWithholding, 2, 0);
    $payment = CustomerPayment::factory()->create([
        'payment_date' => '2026-07-15',
        'gross_settlement_amount' => '100.0000',
        'withholding_amount' => '20.0000',
        'other_deductions' => '0.0000',
        'net_cash_received' => '80.0000',
        'unapplied_amount' => '100.0000',
        'status' => 'draft',
        'created_by' => $context['user']->id,
        'updated_by' => $context['user']->id,
    ]);

    $payment->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $context['user']->id]);

    $journal = JournalEntry::query()->with('lines')->sole();
    expect($journal->total_debit)->toBe('100.0000')
        ->and($journal->total_credit)->toBe('100.0000')
        ->and($journal->lines->pluck('description')->all())
        ->toContain('Cash received', 'Creditable withholding tax', 'Accounts receivable settled');
});

it('enforces source-posting visibility and retry authorization', function (): void {
    $context = automaticPostingContext();
    $invoice = automaticSalesInvoice($context);
    $invoice->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $context['user']->id]);
    $posting = SourcePosting::query()->sole();

    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->get(route('source-postings.index'))->assertSuccessful();
    $this->actingAs($viewer)->post(route('source-postings.retry', $posting))->assertForbidden();

    $encoder = User::factory()->create();
    $encoder->assignRole('Encoder');
    $this->actingAs($encoder)->get(route('source-postings.index'))->assertSuccessful();
    $this->actingAs($encoder)->post(route('source-postings.retry', $posting))->assertForbidden();
});
