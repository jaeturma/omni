<?php

use App\Models\Bir2551qWorksheet;
use App\Models\BusinessProfile;
use App\Models\TaxComplianceRule;
use App\Models\TaxFiling;
use App\Models\TaxFilingAttachment;
use App\Models\TaxObligation;
use App\Models\TaxPeriod;
use App\Models\TaxProfile;
use App\Models\TaxReconciliation;
use App\Models\User;
use App\Services\TaxFilingHistory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function filingHistoryFixture(string $amount = '100.0000', int $revision = 1): array
{
    $user = User::factory()->administrator()->create();
    $business = BusinessProfile::factory()->active()->create();
    $profile = TaxProfile::query()->create(['business_profile_id' => $business->id, 'taxpayer_type' => 'sole_proprietorship', 'registration_type' => 'registered', 'vat_status' => 'non_vat', 'income_tax_option' => 'graduated', 'percentage_tax_registered' => true, 'filing_frequency' => 'quarterly', 'registration_start_date' => '2026-01-01', 'first_filing_period' => '2026-Q1', 'rdo_code' => '050', 'tin' => '123-456-789', 'branch_code' => '00000', 'registered_books_type' => 'manual', 'active' => true]);
    $rule = TaxComplianceRule::query()->create(['tax_profile_id' => $profile->id, 'tax_type' => 'percentage_tax', 'bir_form_number' => '2551Q', 'form_title' => 'BIR Form 2551Q', 'taxpayer_applicability' => 'sole_proprietorship', 'registration_applicability' => 'registered', 'filing_frequency' => 'quarterly', 'applicable_quarters' => [1, 2, 3, 4], 'effective_from' => '2026-01-01', 'tax_base_rule' => 'Reconciled sales', 'credit_rule' => 'Verified credits', 'deadline_rule' => 'Configured deadline', 'amendment_supported' => true, 'official_reference_title' => 'BIR', 'official_reference_url' => 'https://www.bir.gov.ph/', 'last_reviewed_on' => '2026-01-01', 'reviewed_by' => $user->id]);
    $period = TaxPeriod::query()->create(['tax_profile_id' => $profile->id, 'frequency' => 'quarterly', 'period_start' => '2026-01-01', 'period_end' => '2026-03-31', 'capture_start' => '2026-01-01', 'tax_year' => 2026, 'quarter' => 1, 'label' => 'Q1 2026']);
    $obligation = TaxObligation::query()->create(['tax_period_id' => $period->id, 'tax_compliance_rule_id' => $rule->id, 'tax_type' => 'percentage_tax', 'bir_form_number' => '2551Q', 'original_due_date' => '2026-04-25', 'deadline_rule_source' => 'Configured deadline', 'status' => 'ready_to_file', 'rule_snapshot' => []]);
    $reconciliation = TaxReconciliation::query()->create(['tax_obligation_id' => $obligation->id, 'tax_base_rule' => 'Reconciled sales', 'gross_sales' => $amount, 'operational_net_sales' => $amount, 'receipt_basis' => $amount, 'ledger_revenue' => $amount, 'customer_withholding' => '0.0000', 'difference' => '0.0000', 'critical_difference_count' => 0, 'parameters' => [], 'source_snapshot' => [], 'generated_at' => now(), 'generated_by' => $user->id]);
    $worksheet = Bir2551qWorksheet::query()->create(['tax_obligation_id' => $obligation->id, 'tax_reconciliation_id' => $reconciliation->id, 'revision_number' => $revision, 'return_type' => $revision === 1 ? 'original' : 'amended', 'basis_type' => 'accrual', 'return_year' => 2026, 'quarter' => 1, 'status' => 'approved', 'filing_status' => 'not_filed', 'review_status' => 'approved', 'gross_taxable_amount' => $amount, 'taxable_amount' => $amount, 'total_amount_payable' => $amount, 'tax_rate' => '3.000000', 'taxpayer_snapshot' => [], 'rule_snapshot' => [], 'reconciliation_snapshot' => [], 'source_snapshot' => [], 'prepared_by' => $user->id, 'reviewed_at' => now(), 'reviewed_by' => $user->id, 'approved_at' => now(), 'approved_by' => $user->id, 'frozen_at' => now()]);

    return compact('user', 'obligation', 'worksheet');
}

function filingHistoryData(Bir2551qWorksheet $worksheet, string $reference = 'EBIR-001'): array
{
    return ['worksheet_reference' => '2551q:'.$worksheet->id, 'filing_channel' => 'eBIRForms', 'filing_date' => '2026-04-20', 'return_reference' => $reference, 'amount_declared' => $worksheet->total_amount_payable, 'confirm_manual_filing' => '1'];
}

it('requires confirmation, records a reconciled filing, and rejects duplicate worksheet history', function (): void {
    $fixture = filingHistoryFixture();
    $data = filingHistoryData($fixture['worksheet']);

    $this->actingAs($fixture['user'])->post(route('tax-filings.store'), [...$data, 'confirm_manual_filing' => null])->assertSessionHasErrors('confirm_manual_filing');
    $this->actingAs($fixture['user'])->post(route('tax-filings.store'), $data)->assertRedirect();

    $filing = TaxFiling::query()->sole();
    expect($filing->amount_declared)->toBe('100.0000')->and($filing->declared_difference)->toBe('0.0000')
        ->and($fixture['obligation']->fresh()->filing_status)->toBe('filed');

    $this->actingAs($fixture['user'])->post(route('tax-filings.store'), [...$data, 'return_reference' => 'EBIR-002'])->assertSessionHasErrors('worksheet_reference');
});

it('blocks a declared amount that does not reconcile to the frozen worksheet', function (): void {
    $fixture = filingHistoryFixture();

    $this->actingAs($fixture['user'])->post(route('tax-filings.store'), [...filingHistoryData($fixture['worksheet']), 'amount_declared' => '99.9900'])->assertSessionHasErrors('amount_declared');
    expect(TaxFiling::query()->count())->toBe(0);
});

it('tracks partial, full, and excess payments and synchronizes the obligation status', function (): void {
    $fixture = filingHistoryFixture();
    $service = app(TaxFilingHistory::class);
    $filing = $service->recordFiling(['worksheet_type' => '2551q', 'worksheet_id' => $fixture['worksheet']->id, 'filing_channel' => 'eBIRForms', 'filing_date' => '2026-04-20', 'return_reference' => 'PAY-RETURN', 'amount_declared' => '100.0000'], $fixture['user']);

    $service->recordPayment($filing, ['payment_channel' => 'Online', 'payment_date' => '2026-04-20', 'payment_reference' => 'PAY-1', 'amount_paid' => '40.0000'], $fixture['user']);
    expect($filing->fresh()->paymentStatus())->toBe('partially_paid')->and($fixture['obligation']->fresh()->payment_status)->toBe('partially_paid');
    $service->recordPayment($filing, ['payment_channel' => 'Online', 'payment_date' => '2026-04-21', 'payment_reference' => 'PAY-2', 'amount_paid' => '60.0000'], $fixture['user']);
    expect($filing->fresh()->paymentStatus())->toBe('paid');
    $service->recordPayment($filing, ['payment_channel' => 'Online', 'payment_date' => '2026-04-22', 'payment_reference' => 'PAY-3', 'amount_paid' => '1.0000'], $fixture['user']);
    expect($filing->fresh()->paymentStatus())->toBe('overpaid')->and($fixture['obligation']->fresh()->payment_status)->toBe('overpaid');
});

it('links an amended filing only to the original filing for the same obligation', function (): void {
    $fixture = filingHistoryFixture();
    $service = app(TaxFilingHistory::class);
    $original = $service->recordFiling(['worksheet_type' => '2551q', 'worksheet_id' => $fixture['worksheet']->id, 'filing_channel' => 'eBIRForms', 'filing_date' => '2026-04-20', 'return_reference' => 'ORIGINAL', 'amount_declared' => '100.0000'], $fixture['user']);
    $revision = $fixture['worksheet']->replicate(['created_at', 'updated_at']);
    $revision->revision_number = 2;
    $revision->return_type = 'amended';
    $revision->previous_revision_id = $fixture['worksheet']->id;
    $revision->save();

    $amended = $service->recordFiling(['worksheet_type' => '2551q', 'worksheet_id' => $revision->id, 'filing_channel' => 'eBIRForms', 'filing_date' => '2026-04-25', 'return_reference' => 'AMENDED', 'amount_declared' => '100.0000', 'is_amended' => true, 'original_filing_id' => $original->id, 'amendment_reason' => 'Corrected details'], $fixture['user']);
    expect($amended->original_filing_id)->toBe($original->id)->and($amended->is_amended)->toBeTrue();
});

it('stores evidence privately and protects downloads', function (): void {
    Storage::fake('local');
    $fixture = filingHistoryFixture();
    $filing = app(TaxFilingHistory::class)->recordFiling(['worksheet_type' => '2551q', 'worksheet_id' => $fixture['worksheet']->id, 'filing_channel' => 'eBIRForms', 'filing_date' => '2026-04-20', 'return_reference' => 'ATTACH', 'amount_declared' => '100.0000'], $fixture['user']);

    $this->actingAs($fixture['user'])->post(route('tax-filings.attachments.store', $filing), ['attachment_type' => 'proof_of_filing', 'file' => UploadedFile::fake()->create('confirmation.pdf', 100, 'application/pdf')])->assertRedirect();
    $attachment = TaxFilingAttachment::query()->sole();
    Storage::disk('local')->assertExists($attachment->stored_filename);
    $this->actingAs($fixture['user'])->get(route('tax-filing-attachments.download', $attachment))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('tax-filing-attachments.download', $attachment))->assertForbidden();
});

it('enforces authorization and immutable filing evidence records', function (): void {
    $fixture = filingHistoryFixture();
    $filing = app(TaxFilingHistory::class)->recordFiling(['worksheet_type' => '2551q', 'worksheet_id' => $fixture['worksheet']->id, 'filing_channel' => 'eBIRForms', 'filing_date' => '2026-04-20', 'return_reference' => 'LOCKED', 'amount_declared' => '100.0000'], $fixture['user']);
    $unauthorized = User::factory()->create();

    $this->actingAs($unauthorized)->get(route('tax-filings.index'))->assertForbidden();
    $this->actingAs($unauthorized)->post(route('tax-filings.payments.store', $filing), ['payment_channel' => 'Online', 'payment_date' => '2026-04-20', 'payment_reference' => 'NOPE', 'amount_paid' => '1.0000'])->assertForbidden();
    expect(fn () => $filing->update(['notes' => 'changed']))->toThrow(DomainException::class)
        ->and(fn () => $filing->delete())->toThrow(DomainException::class);
});
