<?php

use App\Models\BusinessProfile;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\FiscalPeriod;
use App\Models\GovernmentDeduction;
use App\Models\SalesInvoice;
use App\Models\TaxComplianceRule;
use App\Models\TaxObligation;
use App\Models\TaxPeriod;
use App\Models\TaxProfile;
use App\Models\TaxRateSetting;
use App\Models\TaxReconciliation;
use App\Models\User;
use App\Services\Bir2551qPreparation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function worksheetFixture(string $rate = '3.000000', int $criticalCount = 0): array
{
    $user = User::factory()->administrator()->create();
    $business = BusinessProfile::factory()->active()->create(['registered_business_name' => 'Omni Test Trading']);
    $profile = TaxProfile::query()->create(['business_profile_id' => $business->id, 'taxpayer_type' => 'sole_proprietorship', 'registration_type' => 'registered', 'vat_status' => 'non_vat', 'income_tax_option' => 'graduated', 'percentage_tax_registered' => true, 'filing_frequency' => 'quarterly', 'registration_start_date' => '2026-05-01', 'first_filing_period' => '2026-Q2', 'rdo_code' => '050', 'tin' => '123-456-789', 'branch_code' => '00000', 'registered_books_type' => 'manual', 'active' => true]);
    $profile->forms()->create(['form_code' => '2551Q', 'filing_frequency' => 'quarterly', 'active' => true]);
    $rule = TaxComplianceRule::query()->create(['tax_profile_id' => $profile->id, 'tax_type' => 'percentage_tax', 'bir_form_number' => '2551Q', 'form_title' => 'BIR Form 2551Q', 'taxpayer_applicability' => 'sole_proprietorship', 'registration_applicability' => 'registered', 'filing_frequency' => 'quarterly', 'applicable_quarters' => [1, 2, 3, 4], 'effective_from' => '2026-05-01', 'tax_rate' => $rate, 'tax_base_rule' => 'Use reconciled accrual sales.', 'credit_rule' => 'Verified government percentage-tax withholding only.', 'deadline_rule' => 'Configured deadline.', 'deadline_months_after_period_end' => 1, 'deadline_day' => 25, 'amendment_supported' => true, 'official_reference_title' => 'BIR reference', 'official_reference_url' => 'https://www.bir.gov.ph/', 'last_reviewed_on' => '2026-08-01', 'reviewed_by' => $user->id]);
    $period = TaxPeriod::query()->create(['tax_profile_id' => $profile->id, 'frequency' => 'quarterly', 'period_start' => '2026-04-01', 'period_end' => '2026-06-30', 'capture_start' => '2026-05-01', 'tax_year' => 2026, 'quarter' => 2, 'label' => 'Q2 2026']);
    $obligation = TaxObligation::query()->create(['tax_period_id' => $period->id, 'tax_compliance_rule_id' => $rule->id, 'tax_type' => 'percentage_tax', 'bir_form_number' => '2551Q', 'original_due_date' => '2026-07-25', 'deadline_rule_source' => 'Configured deadline.', 'status' => 'for_review', 'rule_snapshot' => $rule->toArray()]);
    $reconciliation = TaxReconciliation::query()->create(['tax_obligation_id' => $obligation->id, 'tax_base_rule' => 'Use reconciled accrual sales.', 'gross_sales' => '100.0000', 'operational_net_sales' => '100.0000', 'receipt_basis' => '100.0000', 'ledger_revenue' => '100.0000', 'customer_withholding' => '99.0000', 'difference' => '0.0000', 'critical_difference_count' => $criticalCount, 'parameters' => ['capture_start' => '2026-05-01', 'period_end' => '2026-06-30'], 'source_snapshot' => ['issued_invoices' => [], 'collections' => [['id' => 10, 'number' => 'CR-001', 'date' => '2026-05-20', 'gross' => '100.0000']]], 'generated_at' => now(), 'generated_by' => $user->id]);

    return compact('user', 'profile', 'rule', 'period', 'obligation', 'reconciliation');
}

function addWorksheetInvoice(array $fixture, string $amount = '100.0000'): SalesInvoice
{
    $fiscalPeriod = FiscalPeriod::factory()->create(['name' => 'May 2026', 'starts_on' => '2026-05-01', 'ends_on' => '2026-05-31', 'calendar_month' => 5, 'calendar_quarter' => 2]);
    $invoice = SalesInvoice::factory()->for(Customer::factory()->create(['type' => 'government']))->for($fiscalPeriod)->create(['invoice_number' => 'SI-2551Q-001', 'invoice_date' => '2026-05-15', 'due_date' => '2026-06-15', 'gross_amount' => $amount, 'net_sales_amount' => $amount, 'total_receivable' => $amount, 'balance_due' => $amount, 'status' => 'posted', 'posted_at' => now(), 'posted_by' => $fixture['user']->id]);
    $snapshot = $fixture['reconciliation']->source_snapshot;
    $snapshot['issued_invoices'] = [['id' => $invoice->id, 'number' => $invoice->invoice_number, 'date' => '2026-05-15', 'net_sales' => $amount]];
    $fixture['reconciliation']->update(['source_snapshot' => $snapshot]);

    return $invoice;
}

it('resolves the effective rate and calculates gross tax with decimal half-up rounding', function (): void {
    $fixture = worksheetFixture('3.000000');
    addWorksheetInvoice($fixture, '33.3356');

    $worksheet = app(Bir2551qPreparation::class)->create($fixture['obligation'], ['basis_type' => 'accrual', 'return_type' => 'original'], $fixture['user']);

    expect($worksheet->tax_rate)->toBe('3.000000')->and($worksheet->gross_taxable_amount)->toBe('33.3356')
        ->and($worksheet->gross_tax_due)->toBe('1.0001')->and(data_get($worksheet->rule_snapshot, 'resolved_rate.source'))->toBe('tax_compliance_rule');
});

it('uses only verified government percentage-tax withholding and never reduces the base for expenses', function (): void {
    $fixture = worksheetFixture('2.500000');
    $invoice = addWorksheetInvoice($fixture);
    $rate = TaxRateSetting::query()->create(['tax_profile_id' => $fixture['profile']->id, 'tax_type' => 'percentage_tax_withheld', 'rate' => '2.000000', 'effective_from' => '2026-05-01', 'active' => true]);
    foreach ([['percentage_tax_withheld', 'verified', '2.0000', '2026-05-01'], ['expanded_withholding_tax', 'verified', '5.0000', '2026-05-01'], ['percentage_tax_withheld', 'pending', '7.0000', '2026-05-02']] as [$type, $status, $amount, $coveredFrom]) {
        GovernmentDeduction::query()->create(['customer_id' => $invoice->customer_id, 'sales_invoice_id' => $invoice->id, 'tax_rate_setting_id' => $rate->id, 'deduction_type' => $type, 'certificate_type' => '2307', 'certificate_number' => 'CERT-'.$type.'-'.$status, 'certificate_date' => '2026-06-01', 'covered_from' => $coveredFrom, 'covered_to' => '2026-06-30', 'gross_basis' => '100.0000', 'rate' => '2.000000', 'amount' => $amount, 'status' => $status, 'attachment_reference' => 'Evidence.pdf', 'verified_at' => $status === 'verified' ? now() : null, 'verified_by' => $status === 'verified' ? $fixture['user']->id : null, 'created_by' => $fixture['user']->id, 'updated_by' => $fixture['user']->id]);
    }
    Expense::query()->create(['fiscal_period_id' => $invoice->fiscal_period_id, 'expense_date' => '2026-05-20', 'payee_name' => 'Utility Company', 'expense_category' => 'utilities', 'description' => 'Electricity', 'business_purpose' => 'Operations', 'gross_amount' => '5000.0000', 'net_cash_paid' => '5000.0000', 'status' => 'paid', 'created_by' => $fixture['user']->id, 'updated_by' => $fixture['user']->id]);

    $worksheet = app(Bir2551qPreparation::class)->create($fixture['obligation'], ['basis_type' => 'accrual', 'return_type' => 'original'], $fixture['user']);

    expect($worksheet->taxable_amount)->toBe('100.0000')->and($worksheet->gross_tax_due)->toBe('2.5000')
        ->and($worksheet->government_tax_withheld)->toBe('2.0000')->and($worksheet->total_amount_payable)->toBe('0.5000')
        ->and(data_get($worksheet->reconciliation_snapshot, 'customer_withholding'))->toBe('99.0000');
});

it('requires a complete reconciliation and supporting authority for manual amounts', function (): void {
    $fixture = worksheetFixture('3.000000', 1);
    addWorksheetInvoice($fixture);

    expect(fn () => app(Bir2551qPreparation::class)->create($fixture['obligation'], ['basis_type' => 'accrual', 'return_type' => 'original'], $fixture['user']))->toThrow(ValidationException::class);

    $fixture['reconciliation']->update(['critical_difference_count' => 0]);
    $this->actingAs($fixture['user'])->post(route('bir-2551q.store', $fixture['obligation']), ['basis_type' => 'accrual', 'return_type' => 'original', 'surcharge' => '1.0000'])->assertSessionHasErrors(['penalty_authority', 'penalty_evidence']);
});

it('freezes approved snapshots and creates linked original or amended revisions', function (): void {
    $fixture = worksheetFixture();
    addWorksheetInvoice($fixture);
    $service = app(Bir2551qPreparation::class);
    $original = $service->create($fixture['obligation'], ['basis_type' => 'accrual', 'return_type' => 'original'], $fixture['user']);
    $service->submit($original);
    $service->review($original->refresh(), $fixture['user']);
    $service->approve($original->refresh(), $fixture['user']);
    $frozenSnapshot = $original->fresh()->source_snapshot;

    expect(fn () => $original->fresh()->update(['preparation_notes' => 'Changed']))->toThrow(DomainException::class);
    $revision = $service->create($fixture['obligation']->fresh(), ['basis_type' => 'accrual', 'return_type' => 'amended', 'revision_reason' => 'Corrected supporting certificate.'], $fixture['user'], $original->fresh());

    expect($original->fresh()->source_snapshot)->toBe($frozenSnapshot)->and($original->fresh()->frozen_at)->not->toBeNull()
        ->and($revision->revision_number)->toBe(2)->and($revision->previous_revision_id)->toBe($original->id)
        ->and($revision->return_type)->toBe('amended')->and($revision->status)->toBe('draft');
});

it('enforces worksheet permissions', function (): void {
    $fixture = worksheetFixture();
    addWorksheetInvoice($fixture);
    $worksheet = app(Bir2551qPreparation::class)->create($fixture['obligation'], ['basis_type' => 'accrual', 'return_type' => 'original'], $fixture['user']);
    $unauthorized = User::factory()->create();

    $this->actingAs($unauthorized)->get(route('bir-2551q.index'))->assertForbidden();
    $this->actingAs($unauthorized)->get(route('bir-2551q.show', $worksheet))->assertForbidden();
    expect($fixture['user']->can('bir-2551q.approve'))->toBeTrue()->and($fixture['user']->can('bir-2551q.export'))->toBeTrue();
});
