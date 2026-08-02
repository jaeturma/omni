<?php

use App\Enums\AccountClass;
use App\Enums\AccountingSourceType;
use App\Enums\AccountType;
use App\Enums\JournalEntryStatus;
use App\Enums\JournalEntryType;
use App\Enums\NormalBalance;
use App\Models\Account;
use App\Models\BusinessProfile;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\SalesInvoice;
use App\Models\TaxComplianceRule;
use App\Models\TaxObligation;
use App\Models\TaxPeriod;
use App\Models\TaxProfile;
use App\Models\User;
use App\Services\SalesTaxReconciliation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function reconciliationFixture(): array
{
    $user = User::factory()->administrator()->create();
    $profile = TaxProfile::query()->create([
        'business_profile_id' => BusinessProfile::factory()->active()->create()->id, 'taxpayer_type' => 'sole_proprietorship',
        'registration_type' => 'registered', 'vat_status' => 'non_vat', 'income_tax_option' => 'graduated',
        'percentage_tax_registered' => true, 'filing_frequency' => 'quarterly', 'registration_start_date' => '2026-05-01',
        'first_filing_period' => '2026-Q2', 'rdo_code' => '050', 'tin' => '123-456-789', 'branch_code' => '00000',
        'registered_books_type' => 'manual', 'active' => true,
    ]);
    $rule = TaxComplianceRule::query()->create([
        'tax_profile_id' => $profile->id, 'tax_type' => 'percentage_tax', 'bir_form_number' => '2551Q', 'form_title' => 'BIR Form 2551Q',
        'taxpayer_applicability' => 'sole_proprietorship', 'registration_applicability' => 'registered', 'filing_frequency' => 'quarterly',
        'applicable_quarters' => [1, 2, 3, 4], 'effective_from' => '2026-01-01', 'tax_base_rule' => 'Configured accrual basis under review.',
        'credit_rule' => 'Configured credits.', 'deadline_rule' => 'Configured deadline.', 'deadline_months_after_period_end' => 1,
        'deadline_day' => 25, 'official_reference_title' => 'BIR reference', 'official_reference_url' => 'https://www.bir.gov.ph/',
        'last_reviewed_on' => '2026-08-01', 'reviewed_by' => $user->id,
    ]);
    $period = TaxPeriod::query()->create(['tax_profile_id' => $profile->id, 'frequency' => 'quarterly', 'period_start' => '2026-04-01', 'period_end' => '2026-06-30', 'capture_start' => '2026-05-01', 'tax_year' => 2026, 'quarter' => 2, 'label' => 'Q2 2026']);
    $obligation = TaxObligation::query()->create(['tax_period_id' => $period->id, 'tax_compliance_rule_id' => $rule->id, 'tax_type' => 'percentage_tax', 'bir_form_number' => '2551Q', 'original_due_date' => '2026-07-25', 'deadline_rule_source' => 'Configured deadline.', 'status' => 'for_review', 'rule_snapshot' => ['tax_base_rule' => 'Configured accrual basis under review.']]);
    $fiscalPeriod = FiscalPeriod::factory()->create(['name' => 'May 2026', 'starts_on' => '2026-05-01', 'ends_on' => '2026-05-31', 'calendar_month' => 5, 'calendar_quarter' => 2]);

    return compact('user', 'obligation', 'fiscalPeriod');
}

function reconciliationInvoice(array $fixture, Customer $customer, string $number, string $gross, string $discount = '0.0000', string $status = 'posted'): SalesInvoice
{
    $net = bcsub($gross, $discount, 4);

    return SalesInvoice::factory()->for($customer)->for($fixture['fiscalPeriod'])->create(['invoice_number' => $number, 'invoice_date' => '2026-05-15', 'due_date' => '2026-06-15', 'gross_amount' => $gross, 'discount_amount' => $discount, 'net_sales_amount' => $net, 'total_receivable' => $net, 'balance_due' => $net, 'status' => $status, 'posted_at' => $status === 'draft' ? null : now(), 'posted_by' => $status === 'draft' ? null : $fixture['user']->id]);
}

function reconciliationRevenue(array $fixture, string $credit): void
{
    $account = Account::query()->create(['code' => '4010', 'name' => 'Sales Revenue', 'account_class' => AccountClass::Income, 'account_type' => AccountType::SalesIncome, 'normal_balance' => NormalBalance::Credit, 'is_postable' => true]);
    $journal = JournalEntry::query()->create(['journal_number' => 'JRN-REC-001', 'journal_date' => '2026-05-15', 'document_date' => '2026-05-15', 'fiscal_period_id' => $fixture['fiscalPeriod']->id, 'journal_type' => JournalEntryType::Sales, 'source_type' => AccountingSourceType::Manual, 'description' => 'Reconciled revenue', 'total_debit' => $credit, 'total_credit' => $credit, 'status' => JournalEntryStatus::Posted, 'posted_at' => now(), 'posted_by' => $fixture['user']->id, 'created_by' => $fixture['user']->id, 'updated_by' => $fixture['user']->id]);
    $journal->lines()->create(['account_id' => $account->id, 'line_number' => 1, 'debit' => '0.0000', 'credit' => $credit]);
}

it('reconciles gross sales credit adjustments customer classes receipts withholding and ledger revenue', function (): void {
    $fixture = reconciliationFixture();
    reconciliationInvoice($fixture, Customer::factory()->create(['type' => 'government']), 'SI-2026-000001', '1000.0000', '100.0000');
    reconciliationInvoice($fixture, Customer::factory()->create(['type' => 'private']), 'SI-2026-000002', '500.0000');
    reconciliationRevenue($fixture, '1400.0000');
    CustomerPayment::factory()->create(['payment_date' => '2026-05-20', 'gross_settlement_amount' => '1000.0000', 'withholding_amount' => '20.0000', 'net_cash_received' => '980.0000', 'unapplied_amount' => '0.0000', 'status' => 'posted', 'posted_at' => now(), 'posted_by' => $fixture['user']->id]);

    $result = app(SalesTaxReconciliation::class)->generate($fixture['obligation'], $fixture['user']);

    expect($result->gross_sales)->toBe('1500.0000')->and($result->credit_adjustments)->toBe('100.0000')
        ->and($result->operational_net_sales)->toBe('1400.0000')->and($result->ledger_revenue)->toBe('1400.0000')
        ->and($result->receipt_basis)->toBe('1000.0000')->and($result->customer_withholding)->toBe('20.0000')
        ->and($result->source_snapshot['summary']['government_sales'])->toBe('900.0000')
        ->and($result->source_snapshot['summary']['private_sales'])->toBe('500.0000')->and($result->difference)->toBe('0.0000');
});

it('excludes voided invoices and detects gaps duplicates unposted sources and ledger differences', function (): void {
    $fixture = reconciliationFixture();
    $customer = Customer::factory()->create();
    reconciliationInvoice($fixture, $customer, 'SI-2026-000001', '100.0000');
    reconciliationInvoice($fixture, $customer, 'SI-2026-000003', '900.0000', '0.0000', 'voided');
    reconciliationInvoice($fixture, $customer, 'SI 2026 000001', '50.0000', '0.0000', 'draft');
    reconciliationRevenue($fixture, '90.0000');

    $result = app(SalesTaxReconciliation::class)->generate($fixture['obligation'], $fixture['user']);

    expect($result->gross_sales)->toBe('100.0000')->and($result->difference)->toBe('10.0000')
        ->and($result->source_snapshot['invoice_sequence']['missing'])->toContain('SI-2026-000002')
        ->and($result->source_snapshot['invoice_sequence']['duplicates'])->toContain('SI2026000001')
        ->and($result->source_snapshot['unposted']['invoice_ids'])->toHaveCount(1)
        ->and($result->critical_difference_count)->toBeGreaterThanOrEqual(4);
});

it('requires auditable adjustments and assigned reviewer approval before applying them', function (): void {
    $fixture = reconciliationFixture();
    reconciliationInvoice($fixture, Customer::factory()->create(), 'SI-2026-000001', '100.0000');
    reconciliationRevenue($fixture, '90.0000');
    $reconciliation = app(SalesTaxReconciliation::class)->generate($fixture['obligation'], $fixture['user']);
    $reviewer = User::factory()->create();
    $reviewer->givePermissionTo('tax-reconciliation.review');

    $this->actingAs($fixture['user'])->post(route('tax-reconciliations.adjustments.store', $reconciliation), ['amount' => '-10.0000', 'reviewer_id' => $reviewer->id])->assertSessionHasErrors(['reason', 'evidence_reference']);
    $this->actingAs($fixture['user'])->post(route('tax-reconciliations.adjustments.store', $reconciliation), ['amount' => '-10.0000', 'reason' => 'Supported timing adjustment', 'evidence_reference' => 'JV-2026-001', 'reviewer_id' => $reviewer->id])->assertSessionHasNoErrors();
    $adjustment = $reconciliation->adjustments()->sole();
    expect($reconciliation->fresh()->approved_adjustments)->toBe('0.0000');

    $this->actingAs($reviewer)->patch(route('tax-reconciliations.adjustments.review', $adjustment), ['status' => 'approved', 'review_notes' => 'Evidence checked.'])->assertSessionHasNoErrors();
    $reviewedAdjustment = $adjustment->fresh();
    expect($reviewedAdjustment->reviewed_by)->toBe($reviewer->id)->and($reviewedAdjustment->status)->toBe('approved')
        ->and($reconciliation->fresh()->approved_adjustments)->toBe('-10.0000')->and($reconciliation->fresh()->difference)->toBe('0.0000');
});

it('enforces permissions and blocks ready to file while critical differences remain', function (): void {
    $fixture = reconciliationFixture();
    reconciliationInvoice($fixture, Customer::factory()->create(), 'SI-2026-000001', '100.0000');
    reconciliationRevenue($fixture, '90.0000');
    $reconciliation = app(SalesTaxReconciliation::class)->generate($fixture['obligation'], $fixture['user']);
    $viewer = User::factory()->create();

    $this->actingAs($viewer)->get(route('tax-reconciliations.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('tax-reconciliations.show', $reconciliation))->assertForbidden();
    $this->actingAs($fixture['user'])->patch(route('tax-calendar.update', $fixture['obligation']), ['status' => 'ready_to_file'])->assertSessionHasErrors('status');
    expect($fixture['user']->can('tax-reconciliation.export'))->toBeTrue();
});
