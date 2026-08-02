<?php

use App\Models\BusinessProfile;
use App\Models\TaxComplianceRule;
use App\Models\TaxObligation;
use App\Models\TaxPeriod;
use App\Models\TaxProfile;
use App\Models\User;
use App\Reports\IncomeStatementReport;
use App\Services\Bir1701qPreparation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function bir1701qFixture(array $parameters = [], string $option = 'graduated'): array
{
    $user = User::factory()->administrator()->create();
    $business = BusinessProfile::factory()->active()->create(['registered_business_name' => 'Omni Test Trading']);
    $profile = TaxProfile::query()->create(['business_profile_id' => $business->id, 'taxpayer_type' => 'sole_proprietorship', 'registration_type' => 'registered', 'vat_status' => 'non_vat', 'income_tax_option' => $option, 'percentage_tax_registered' => true, 'filing_frequency' => 'quarterly', 'registration_start_date' => '2026-01-01', 'first_filing_period' => '2026-Q1', 'rdo_code' => '050', 'tin' => '123-456-789', 'branch_code' => '00000', 'registered_books_type' => 'manual', 'active' => true]);
    $profile->forms()->create(['form_code' => '1701Q', 'filing_frequency' => 'quarterly', 'active' => true]);
    $parameters += ['supported_income_tax_options' => ['graduated'], 'deduction_method' => 'itemized', 'computation_type' => 'graduated_brackets', 'brackets' => [['over' => '0', 'not_over' => null, 'base_tax' => '0', 'rate' => '10.000000']]];
    $rule = TaxComplianceRule::query()->create(['tax_profile_id' => $profile->id, 'tax_type' => 'income_tax', 'bir_form_number' => '1701Q', 'form_title' => 'BIR Form 1701Q', 'taxpayer_applicability' => 'sole_proprietorship', 'registration_applicability' => 'registered', 'filing_frequency' => 'quarterly', 'applicable_quarters' => [1, 2, 3], 'effective_from' => '2026-01-01', 'tax_base_rule' => 'Cumulative income statement.', 'credit_rule' => 'Evidence-backed credits.', 'calculation_parameters' => $parameters, 'deadline_rule' => 'Configured deadline.', 'deadline_months_after_period_end' => 1, 'deadline_day' => 15, 'amendment_supported' => true, 'official_reference_title' => 'BIR reference', 'official_reference_url' => 'https://www.bir.gov.ph/', 'last_reviewed_on' => '2026-08-01', 'reviewed_by' => $user->id]);
    $period = TaxPeriod::query()->create(['tax_profile_id' => $profile->id, 'frequency' => 'quarterly', 'period_start' => '2026-04-01', 'period_end' => '2026-06-30', 'capture_start' => '2026-04-01', 'tax_year' => 2026, 'quarter' => 2, 'label' => 'Q2 2026']);
    $obligation = TaxObligation::query()->create(['tax_period_id' => $period->id, 'tax_compliance_rule_id' => $rule->id, 'tax_type' => 'income_tax', 'bir_form_number' => '1701Q', 'original_due_date' => '2026-08-15', 'deadline_rule_source' => 'Configured deadline.', 'status' => 'for_review', 'rule_snapshot' => $rule->toArray()]);

    return compact('user', 'profile', 'rule', 'period', 'obligation');
}

function mockIncomeStatement(): void
{
    $sections = collect(['revenue', 'contra_revenue', 'cost_of_sales', 'operating_expenses', 'other_income', 'other_expenses', 'income_tax'])->map(fn (string $key): array => ['key' => $key, 'label' => str($key)->headline()->toString(), 'total' => '0.0000', 'rows' => collect()]);
    test()->mock(IncomeStatementReport::class)->shouldReceive('generate')->andReturn([
        'summary' => ['revenue' => '1000.0000', 'contra_revenue' => '100.0000', 'net_sales' => '900.0000', 'cost_of_sales' => '300.0000', 'gross_profit' => '600.0000', 'operating_expenses' => '100.0000', 'operating_income' => '500.0000', 'other_income' => '50.0000', 'other_expenses' => '50.0000', 'income_tax' => '0.0000', 'net_income_before_tax' => '500.0000', 'net_income_after_tax' => '500.0000'],
        'sections' => $sections, 'reconciliation_difference' => '0.0000',
    ]);
}

it('calculates cumulative itemized taxable income using only an explicitly configured method', function (): void {
    $fixture = bir1701qFixture();
    mockIncomeStatement();
    $worksheet = app(Bir1701qPreparation::class)->create($fixture['obligation'], ['return_type' => 'original'], $fixture['user']);

    expect($worksheet->cumulative_gross_sales)->toBe('1000.0000')->and($worksheet->gross_income)->toBe('650.0000')
        ->and($worksheet->financial_itemized_deductions)->toBe('150.0000')->and($worksheet->taxable_income)->toBe('500.0000')
        ->and($worksheet->income_tax_due)->toBe('50.0000')->and($worksheet->total_amount_payable)->toBe('50.0000')
        ->and(data_get($worksheet->financial_report_snapshot, 'parameters.start_date'))->toBe('2026-01-01');
});

it('rejects an unregistered or unsupported income tax option instead of inferring the 8 percent option', function (): void {
    $fixture = bir1701qFixture([], 'eight_percent');
    mockIncomeStatement();

    expect(fn () => app(Bir1701qPreparation::class)->create($fixture['obligation'], ['return_type' => 'original'], $fixture['user']))->toThrow(ValidationException::class);
    $fixture['profile']->forms()->update(['active' => false]);
    expect(fn () => app(Bir1701qPreparation::class)->create($fixture['obligation'], ['return_type' => 'original'], $fixture['user']))->toThrow(ValidationException::class);
});

it('requires audit evidence for manual adjustments payments credits and penalties', function (): void {
    $fixture = bir1701qFixture();
    $this->actingAs($fixture['user'])->post(route('bir-1701q.store', $fixture['obligation']), ['return_type' => 'original', 'manual_deduction_adjustment' => '1.0000', 'prior_quarter_payments' => '1.0000', 'manual_creditable_withholding' => '1.0000', 'other_allowable_credits' => '1.0000', 'surcharge' => '1.0000'])
        ->assertSessionHasErrors(['manual_adjustment_reason', 'manual_adjustment_evidence', 'prior_payment_evidence', 'withholding_evidence', 'other_credits_authority', 'other_credits_evidence', 'penalty_authority', 'penalty_evidence']);
});

it('carries the latest frozen prior-quarter figures into the cumulative worksheet', function (): void {
    $fixture = bir1701qFixture();
    mockIncomeStatement();
    $q1 = TaxPeriod::query()->create(['tax_profile_id' => $fixture['profile']->id, 'frequency' => 'quarterly', 'period_start' => '2026-01-01', 'period_end' => '2026-03-31', 'capture_start' => '2026-01-01', 'tax_year' => 2026, 'quarter' => 1, 'label' => 'Q1 2026']);
    $q1Obligation = TaxObligation::query()->create(['tax_period_id' => $q1->id, 'tax_compliance_rule_id' => $fixture['rule']->id, 'tax_type' => 'income_tax', 'bir_form_number' => '1701Q', 'original_due_date' => '2026-05-15', 'deadline_rule_source' => 'Configured deadline.', 'status' => 'for_review', 'rule_snapshot' => $fixture['rule']->toArray()]);
    $service = app(Bir1701qPreparation::class);
    $prior = $service->create($q1Obligation, ['return_type' => 'original'], $fixture['user']);
    $service->submit($prior);
    $service->review($prior, $fixture['user']);
    $service->approve($prior, $fixture['user']);

    $current = $service->create($fixture['obligation'], ['return_type' => 'original'], $fixture['user']);
    expect($current->prior_quarter_taxable_income)->toBe('500.0000')->and($current->prior_quarter_income_tax_due)->toBe('50.0000')
        ->and(data_get($current->prior_quarter_snapshot, 'quarter'))->toBe(1);
});

it('freezes an approved worksheet and preserves linked revisions', function (): void {
    $fixture = bir1701qFixture();
    mockIncomeStatement();
    $service = app(Bir1701qPreparation::class);
    $original = $service->create($fixture['obligation'], ['return_type' => 'original'], $fixture['user']);
    $service->submit($original);
    expect($original->fresh()->status)->toBe('for_review');
    $service->review($original->refresh(), $fixture['user']);
    $service->approve($original->refresh(), $fixture['user']);

    expect(fn () => $original->fresh()->update(['preparation_notes' => 'Changed']))->toThrow(DomainException::class);
    $revision = $service->create($fixture['obligation']->fresh(), ['return_type' => 'amended', 'revision_reason' => 'Corrected evidence.'], $fixture['user'], $original->fresh());
    expect($original->fresh()->frozen_at)->not->toBeNull()->and($revision->revision_number)->toBe(2)->and($revision->previous_revision_id)->toBe($original->id)->and($revision->status)->toBe('draft');
});

it('enforces 1701Q worksheet permissions', function (): void {
    $fixture = bir1701qFixture();
    mockIncomeStatement();
    $worksheet = app(Bir1701qPreparation::class)->create($fixture['obligation'], ['return_type' => 'original'], $fixture['user']);
    $unauthorized = User::factory()->create();

    $this->actingAs($unauthorized)->get(route('bir-1701q.index'))->assertForbidden();
    $this->actingAs($unauthorized)->get(route('bir-1701q.show', $worksheet))->assertForbidden();
    expect($fixture['user']->can('bir-1701q.approve'))->toBeTrue()->and($fixture['user']->can('bir-1701q.export'))->toBeTrue();
});
