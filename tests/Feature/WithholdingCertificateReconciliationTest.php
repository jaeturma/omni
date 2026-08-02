<?php

use App\Enums\AccountClass;
use App\Enums\AccountType;
use App\Enums\GovernmentDeductionStatus;
use App\Enums\NormalBalance;
use App\Enums\SalesInvoiceStatus;
use App\Models\Account;
use App\Models\BusinessProfile;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\GovernmentDeduction;
use App\Models\JournalEntry;
use App\Models\SalesInvoice;
use App\Models\TaxComplianceRule;
use App\Models\TaxObligation;
use App\Models\TaxPeriod;
use App\Models\TaxProfile;
use App\Models\TaxRateSetting;
use App\Models\User;
use App\Services\WithholdingCertificateReconciliation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function withholdingFixture(bool $partial = true): array
{
    $user = User::factory()->administrator()->create();
    $customer = Customer::factory()->create(['type' => 'government', 'name' => 'DepEd Office']);
    $business = BusinessProfile::factory()->active()->create();
    $profile = TaxProfile::query()->create(['business_profile_id' => $business->id, 'taxpayer_type' => 'sole_proprietorship', 'registration_type' => 'registered', 'vat_status' => 'non_vat', 'income_tax_option' => 'graduated', 'percentage_tax_registered' => true, 'filing_frequency' => 'quarterly', 'registration_start_date' => '2026-01-01', 'first_filing_period' => '2026-Q1', 'rdo_code' => '050', 'tin' => '123-456-789', 'branch_code' => '00000', 'registered_books_type' => 'manual', 'active' => true]);
    $rate = TaxRateSetting::query()->create(['tax_profile_id' => $profile->id, 'tax_type' => 'expanded_withholding_tax', 'rate' => '2.000000', 'effective_from' => '2026-01-01', 'active' => true]);
    $fiscalPeriod = FiscalPeriod::factory()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']);
    $invoice = SalesInvoice::factory()->for($customer)->for($fiscalPeriod)->create(['invoice_number' => 'SI-WHT-001', 'invoice_date' => '2026-05-15', 'due_date' => '2026-06-15', 'gross_amount' => '10000.0000', 'net_sales_amount' => '10000.0000', 'expected_withholding_amount' => '200.0000', 'total_receivable' => '9800.0000', 'balance_due' => '9800.0000', 'status' => SalesInvoiceStatus::Posted, 'posted_at' => now(), 'posted_by' => $user->id]);
    $rule = TaxComplianceRule::query()->create(['tax_profile_id' => $profile->id, 'tax_type' => 'income_tax', 'bir_form_number' => '1701Q', 'form_title' => '1701Q', 'taxpayer_applicability' => 'sole_proprietorship', 'registration_applicability' => 'registered', 'filing_frequency' => 'quarterly', 'applicable_quarters' => [1, 2, 3], 'effective_from' => '2026-01-01', 'tax_base_rule' => 'Configured', 'credit_rule' => 'Evidence-backed', 'calculation_parameters' => ['allow_partial_withholding_application' => $partial], 'deadline_rule' => 'Configured', 'deadline_months_after_period_end' => 1, 'deadline_day' => 15, 'amendment_supported' => true, 'official_reference_title' => 'BIR', 'official_reference_url' => 'https://www.bir.gov.ph/', 'last_reviewed_on' => '2026-08-01', 'reviewed_by' => $user->id]);
    $period = TaxPeriod::query()->create(['tax_profile_id' => $profile->id, 'frequency' => 'quarterly', 'period_start' => '2026-04-01', 'period_end' => '2026-06-30', 'capture_start' => '2026-04-01', 'tax_year' => 2026, 'quarter' => 2, 'label' => 'Q2 2026']);
    $obligation = TaxObligation::query()->create(['tax_period_id' => $period->id, 'tax_compliance_rule_id' => $rule->id, 'tax_type' => 'income_tax', 'bir_form_number' => '1701Q', 'original_due_date' => '2026-08-15', 'deadline_rule_source' => 'Configured', 'status' => 'for_review', 'rule_snapshot' => $rule->toArray()]);
    $certificate = GovernmentDeduction::query()->create(['customer_id' => $customer->id, 'sales_invoice_id' => $invoice->id, 'tax_rate_setting_id' => $rate->id, 'deduction_type' => 'expanded_withholding_tax', 'certificate_type' => '2307', 'certificate_number' => 'CERT-001', 'certificate_date' => '2026-06-30', 'covered_from' => '2026-04-01', 'covered_to' => '2026-06-30', 'gross_basis' => '10000.0000', 'rate' => '2.000000', 'amount' => '200.0000', 'status' => GovernmentDeductionStatus::Verified, 'attachment_reference' => '2307.pdf', 'verified_at' => now(), 'verified_by' => $user->id, 'created_by' => $user->id, 'updated_by' => $user->id]);

    return compact('user', 'customer', 'invoice', 'certificate', 'obligation');
}

it('matches source transactions and blocks duplicate certificate records', function (): void {
    $fixture = withholdingFixture();
    expect($fixture['certificate']->salesInvoice->is($fixture['invoice']))->toBeTrue()->and($fixture['certificate']->customer->is($fixture['customer']))->toBeTrue();
    $this->actingAs($fixture['user'])->post(route('government-deductions.store'), ['sales_invoice_id' => $fixture['invoice']->id, 'tax_rate_setting_id' => $fixture['certificate']->tax_rate_setting_id, 'deduction_type' => 'expanded_withholding_tax', 'certificate_type' => '2307', 'certificate_number' => 'CERT-001', 'certificate_date' => '2026-06-30', 'covered_from' => '2026-04-01', 'covered_to' => '2026-06-30', 'gross_basis' => '10000.0000'])->assertSessionHasErrors();
});

it('supports evidence-backed partial and full application without over-application', function (): void {
    $fixture = withholdingFixture();
    $service = app(WithholdingCertificateReconciliation::class);
    $first = $service->apply($fixture['certificate'], $fixture['obligation'], ['amount' => '75.0000', 'evidence_reference' => 'worksheet-q2.pdf'], $fixture['user']);
    expect($first->amount)->toBe('75.0000')->and($fixture['certificate']->fresh()->remainingAmount())->toBe('125.0000')->and($fixture['certificate']->fresh()->status)->toBe(GovernmentDeductionStatus::Verified);
    expect(fn () => $service->apply($fixture['certificate'], $fixture['obligation'], ['amount' => '1.0000', 'evidence_reference' => 'duplicate.pdf'], $fixture['user']))->toThrow(ValidationException::class);

    $second = $fixture['certificate']->replicate();
    $second->certificate_number = 'CERT-002';
    $second->certificate_type = '2306';
    $second->deduction_type = 'percentage_tax_withheld';
    $second->save();
    $service->apply($second, $fixture['obligation'], ['amount' => '200.0000', 'evidence_reference' => 'full.pdf'], $fixture['user']);
    expect($second->fresh()->status)->toBe(GovernmentDeductionStatus::Applied)->and($second->fresh()->remainingAmount())->toBe('0.0000');
});

it('blocks partial application unless enabled by the effective return rule', function (): void {
    $fixture = withholdingFixture(false);
    expect(fn () => app(WithholdingCertificateReconciliation::class)->apply($fixture['certificate'], $fixture['obligation'], ['amount' => '50.0000', 'evidence_reference' => 'partial.pdf'], $fixture['user']))->toThrow(ValidationException::class);
});

it('shows missing and unapplied certificates with accounting differences', function (): void {
    $fixture = withholdingFixture();
    $other = SalesInvoice::factory()->for($fixture['customer'])->for($fixture['invoice']->fiscalPeriod)->create(['invoice_number' => 'SI-MISSING', 'invoice_date' => '2026-07-01', 'due_date' => '2026-08-01', 'expected_withholding_amount' => '50.0000', 'status' => SalesInvoiceStatus::Posted, 'posted_at' => now(), 'posted_by' => $fixture['user']->id]);
    $summary = app(WithholdingCertificateReconciliation::class)->summary(2026);
    expect($summary['unapplied_count'])->toBe(1)->and($summary['certificate_total'])->toBe('200.0000')->and($summary['ledger_total'])->toBe('0.0000')->and($summary['difference'])->toBe('200.0000')->and($summary['missing']->contains($other))->toBeTrue();
});

it('reconciles verified certificates to a matched posted accounting control line', function (): void {
    $fixture = withholdingFixture();
    $account = Account::query()->create(['code' => '1125', 'name' => 'Creditable withholding tax', 'account_class' => AccountClass::Asset, 'account_type' => AccountType::PrepaidExpense, 'normal_balance' => NormalBalance::Debit, 'is_control_account' => true, 'control_account_type' => 'creditable_withholding_tax']);
    $entry = JournalEntry::query()->create(['journal_number' => 'JV-WHT-001', 'journal_date' => '2026-06-30', 'document_date' => '2026-06-30', 'fiscal_period_id' => $fixture['invoice']->fiscal_period_id, 'journal_type' => 'adjustment', 'source_type' => 'manual', 'description' => 'Recognize certificate', 'total_debit' => '200.0000', 'total_credit' => '200.0000', 'status' => 'posted', 'posted_at' => now(), 'posted_by' => $fixture['user']->id, 'created_by' => $fixture['user']->id, 'updated_by' => $fixture['user']->id]);
    $line = $entry->lines()->create(['account_id' => $account->id, 'line_number' => 1, 'description' => 'CWT asset', 'debit' => '200.0000', 'credit' => '0.0000', 'customer_id' => $fixture['customer']->id]);
    $fixture['certificate']->update(['journal_entry_line_id' => $line->id]);

    $summary = app(WithholdingCertificateReconciliation::class)->summary(2026);
    expect($summary['certificate_total'])->toBe('200.0000')->and($summary['ledger_total'])->toBe('200.0000')->and($summary['difference'])->toBe('0.0000');
});

it('supports rejection and enforces reconciliation authorization', function (): void {
    $fixture = withholdingFixture();
    $fixture['certificate']->update(['status' => GovernmentDeductionStatus::Received]);
    $this->actingAs($fixture['user'])->get(route('government-deductions.show', $fixture['certificate']))->assertSuccessful()->assertSee('Reject certificate');
    $this->actingAs($fixture['user'])->patch(route('government-deductions.transition', $fixture['certificate']), ['status' => 'rejected', 'reason' => 'Invalid gross basis'])->assertSessionHasNoErrors();
    expect($fixture['certificate']->fresh()->status)->toBe(GovernmentDeductionStatus::Rejected)->and($fixture['certificate']->fresh()->rejection_reason)->toBe('Invalid gross basis');
    $this->actingAs(User::factory()->create())->get(route('withholding-reconciliation.index'))->assertForbidden();
});
