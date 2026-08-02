<?php

use App\Models\Bir2551qWorksheet;
use App\Models\BusinessProfile;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\GovernmentDeduction;
use App\Models\SalesInvoice;
use App\Models\TaxComplianceRule;
use App\Models\TaxFiling;
use App\Models\TaxObligation;
use App\Models\TaxPeriod;
use App\Models\TaxProfile;
use App\Models\TaxRateSetting;
use App\Models\TaxReconciliation;
use App\Models\TaxReviewComment;
use App\Models\User;
use App\Services\TaxDashboardReviewPack;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function taxDashboardFixture(int $critical = 0): array
{
    $user = User::factory()->administrator()->create();
    $business = BusinessProfile::factory()->active()->create(['registered_business_name' => 'Omni Review Trading', 'tin' => '123-456-789']);
    $profile = TaxProfile::query()->create(['business_profile_id' => $business->id, 'taxpayer_type' => 'sole_proprietorship', 'registration_type' => 'registered', 'vat_status' => 'non_vat', 'income_tax_option' => 'graduated', 'percentage_tax_registered' => true, 'filing_frequency' => 'quarterly', 'registration_start_date' => '2026-01-01', 'first_filing_period' => '2026-Q1', 'rdo_code' => '050', 'tin' => '123-456-789', 'branch_code' => '00000', 'registered_books_type' => 'manual', 'active' => true]);
    $rule = TaxComplianceRule::query()->create(['tax_profile_id' => $profile->id, 'tax_type' => 'percentage_tax', 'bir_form_number' => '2551Q', 'form_title' => 'BIR Form 2551Q', 'taxpayer_applicability' => 'sole_proprietorship', 'registration_applicability' => 'registered', 'filing_frequency' => 'quarterly', 'applicable_quarters' => [1, 2, 3, 4], 'effective_from' => '2026-01-01', 'tax_base_rule' => 'Reconciled sales', 'credit_rule' => 'Verified credits', 'deadline_rule' => 'Configured', 'amendment_supported' => true, 'official_reference_title' => 'BIR', 'official_reference_url' => 'https://www.bir.gov.ph/', 'last_reviewed_on' => '2026-07-01', 'reviewed_by' => $user->id]);
    $period = TaxPeriod::query()->create(['tax_profile_id' => $profile->id, 'frequency' => 'quarterly', 'period_start' => '2026-04-01', 'period_end' => '2026-06-30', 'capture_start' => '2026-04-01', 'tax_year' => 2026, 'quarter' => 2, 'label' => 'Q2 2026']);
    $obligation = TaxObligation::query()->create(['tax_period_id' => $period->id, 'tax_compliance_rule_id' => $rule->id, 'tax_type' => 'percentage_tax', 'bir_form_number' => '2551Q', 'original_due_date' => '2026-07-25', 'deadline_rule_source' => 'Configured', 'status' => 'ready_to_file', 'rule_snapshot' => []]);
    $reconciliation = TaxReconciliation::query()->create(['tax_obligation_id' => $obligation->id, 'tax_base_rule' => 'Reconciled sales', 'gross_sales' => '100.0000', 'operational_net_sales' => '100.0000', 'receipt_basis' => '90.0000', 'ledger_revenue' => '100.0000', 'customer_withholding' => '0.0000', 'difference' => '0.0000', 'critical_difference_count' => $critical, 'parameters' => [], 'source_snapshot' => [], 'generated_at' => now(), 'generated_by' => $user->id]);
    $worksheet = Bir2551qWorksheet::query()->create(['tax_obligation_id' => $obligation->id, 'tax_reconciliation_id' => $reconciliation->id, 'revision_number' => 1, 'return_type' => 'original', 'basis_type' => 'accrual', 'return_year' => 2026, 'quarter' => 2, 'status' => 'approved', 'filing_status' => 'not_filed', 'review_status' => 'approved', 'gross_taxable_amount' => '100.0000', 'taxable_amount' => '100.0000', 'total_amount_payable' => '3.0000', 'tax_rate' => '3.000000', 'taxpayer_snapshot' => [], 'rule_snapshot' => [], 'reconciliation_snapshot' => [], 'source_snapshot' => [], 'prepared_by' => $user->id, 'approved_at' => now(), 'approved_by' => $user->id, 'frozen_at' => now()]);

    return compact('user', 'business', 'profile', 'period', 'obligation', 'worksheet');
}

it('calculates period indicators and blocks ready to file when reconciliation is critical', function (): void {
    $fixture = taxDashboardFixture(1);
    $data = app(TaxDashboardReviewPack::class)->build($fixture['period']);

    expect($data['indicators']['overdue'])->toBe(1)->and($data['indicators']['unreconciled_sales'])->toBe(1)
        ->and($data['indicators']['ready_to_file'])->toBe(0)->and($data['critical_blocker'])->toBeTrue();

    $this->actingAs($fixture['user'])->get(route('tax-dashboard.show', $fixture['period']))->assertSuccessful()->assertSee('Ready-to-file indication is blocked');
});

it('reports missing certificates and missing filing or payment proof', function (): void {
    $fixture = taxDashboardFixture();
    $customer = Customer::factory()->create();
    $invoice = SalesInvoice::factory()->for($customer)->for(FiscalPeriod::factory()->create(['starts_on' => '2026-04-01', 'ends_on' => '2026-04-30']))->create(['invoice_date' => '2026-04-10']);
    $rate = TaxRateSetting::query()->create(['tax_profile_id' => $fixture['profile']->id, 'tax_type' => 'expanded_withholding_tax', 'rate' => '1.000000', 'effective_from' => '2026-01-01', 'active' => true]);
    GovernmentDeduction::query()->create(['customer_id' => $customer->id, 'sales_invoice_id' => $invoice->id, 'tax_rate_setting_id' => $rate->id, 'deduction_type' => 'expanded_withholding_tax', 'certificate_type' => '2307', 'covered_from' => '2026-04-01', 'covered_to' => '2026-06-30', 'gross_basis' => '100.0000', 'rate' => '1.000000', 'amount' => '1.0000', 'status' => 'pending', 'created_by' => $fixture['user']->id, 'updated_by' => $fixture['user']->id]);
    TaxFiling::query()->create(['tax_obligation_id' => $fixture['obligation']->id, 'bir2551q_worksheet_id' => $fixture['worksheet']->id, 'bir_form_number' => '2551Q', 'worksheet_revision' => 1, 'filing_channel' => 'eBIRForms', 'filing_date' => '2026-07-20', 'return_reference' => 'REF-1', 'worksheet_amount_payable' => '3.0000', 'amount_declared' => '3.0000', 'declared_difference' => '0.0000', 'confirmed_at' => now(), 'filed_by' => $fixture['user']->id]);

    $indicators = app(TaxDashboardReviewPack::class)->build($fixture['period'])['indicators'];
    expect($indicators['missing_certificates'])->toBe(1)->and($indicators['missing_filing_or_payment_proof'])->toBe(1);
});

it('includes applicable schedules and masks sensitive identifiers on the review screen', function (): void {
    $fixture = taxDashboardFixture();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::findByName('tax-dashboard.view'));

    $this->actingAs($viewer)->get(route('tax-dashboard.show', $fixture['period']))->assertSuccessful()->assertDontSee('123-456-789');
    $this->actingAs($fixture['user'])->get(route('tax-review-pack.print', $fixture['period']))->assertSuccessful()->assertSee('2551Q worksheet')->assertSee('Books and schedules index')->assertSee('Preparer and reviewer sign-off');
    $this->actingAs($fixture['user'])->get(route('tax-review-pack.download', $fixture['period']))->assertSuccessful()->assertHeader('content-type', 'text/html; charset=UTF-8');
});

it('requires an explicit valid period and does not create filing side effects', function (): void {
    $fixture = taxDashboardFixture();
    $before = TaxFiling::query()->count();

    $this->actingAs($fixture['user'])->get('/tax-dashboard/999999')->assertNotFound();
    $this->actingAs($fixture['user'])->get(route('tax-dashboard.show', $fixture['period']))->assertSuccessful();
    expect(TaxFiling::query()->count())->toBe($before);
});

it('manages review comments with permission and period scoping', function (): void {
    $fixture = taxDashboardFixture();
    $this->actingAs($fixture['user'])->post(route('tax-review-comments.store', $fixture['period']), ['status' => 'open', 'comment' => 'Confirm certificate support.'])->assertRedirect();
    $comment = TaxReviewComment::query()->sole();
    $this->actingAs($fixture['user'])->post(route('tax-review-comments.store', $fixture['period']), ['status' => 'resolved', 'comment_id' => $comment->id])->assertRedirect();
    expect($comment->fresh()->status)->toBe('resolved')->and($comment->fresh()->resolved_by)->toBe($fixture['user']->id);

    $this->actingAs(User::factory()->create())->get(route('tax-dashboard.show', $fixture['period']))->assertForbidden();
});
