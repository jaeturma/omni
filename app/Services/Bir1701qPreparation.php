<?php

namespace App\Services;

use App\Enums\GovernmentDeductionStatus;
use App\Models\Bir1701qWorksheet;
use App\Models\GovernmentDeduction;
use App\Models\TaxObligation;
use App\Models\TaxProfile;
use App\Models\User;
use App\Reports\IncomeStatementReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Bir1701qPreparation
{
    public const PERMISSIONS = ['bir-1701q.view', 'bir-1701q.prepare', 'bir-1701q.review', 'bir-1701q.approve', 'bir-1701q.revise', 'bir-1701q.export'];

    public function __construct(private IncomeStatementReport $incomeStatement, private TaxComplianceCalendar $calendar) {}

    /** @param array<string, mixed> $data */
    public function create(TaxObligation $obligation, array $data, User $user, ?Bir1701qWorksheet $previous = null): Bir1701qWorksheet
    {
        return DB::transaction(function () use ($obligation, $data, $user, $previous): Bir1701qWorksheet {
            $obligation->loadMissing(['taxPeriod.taxProfile.businessProfile', 'taxComplianceRule']);
            $this->assertEligible($obligation, $previous, (string) ($data['return_type'] ?? 'original'));

            return Bir1701qWorksheet::query()->create($this->worksheetData($obligation, $data) + [
                'tax_obligation_id' => $obligation->id, 'previous_revision_id' => $previous?->id,
                'revision_number' => ((int) $obligation->bir1701qWorksheets()->max('revision_number')) + 1,
                'return_type' => $data['return_type'] ?? 'original', 'revision_reason' => $data['revision_reason'] ?? null,
                'prepared_by' => $user->id,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Bir1701qWorksheet $worksheet, array $data): Bir1701qWorksheet
    {
        if ($worksheet->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only draft 1701Q worksheets may be edited.']);
        }
        $worksheet->taxObligation->loadMissing(['taxPeriod.taxProfile.businessProfile', 'taxComplianceRule']);
        $worksheet->update($this->worksheetData($worksheet->taxObligation, $data));

        return $worksheet->refresh();
    }

    public function submit(Bir1701qWorksheet $worksheet): void
    {
        $worksheet->refresh();
        if ($worksheet->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only draft worksheets may be submitted for review.']);
        }
        $worksheet->update(['status' => 'for_review', 'review_status' => 'pending']);
    }

    public function review(Bir1701qWorksheet $worksheet, User $user): void
    {
        $worksheet->refresh();
        if ($worksheet->status !== 'for_review') {
            throw ValidationException::withMessages(['status' => 'Only worksheets awaiting review may be reviewed.']);
        }
        $worksheet->update(['status' => 'reviewed', 'review_status' => 'reviewed', 'reviewed_at' => now(), 'reviewed_by' => $user->id]);
    }

    public function approve(Bir1701qWorksheet $worksheet, User $user): void
    {
        DB::transaction(function () use ($worksheet, $user): void {
            $worksheet->refresh();
            if ($worksheet->status !== 'reviewed') {
                throw ValidationException::withMessages(['status' => 'The worksheet must be reviewed before approval.']);
            }
            $worksheet->update(['status' => 'ready_to_file', 'review_status' => 'approved', 'approved_at' => now(), 'approved_by' => $user->id, 'frozen_at' => now()]);
            $this->calendar->update($worksheet->taxObligation, ['status' => 'ready_to_file']);
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function worksheetData(TaxObligation $obligation, array $data): array
    {
        $period = $obligation->taxPeriod;
        $profile = $period->taxProfile;
        $rule = $obligation->taxComplianceRule;
        $parameters = $this->parameters($obligation);
        $start = max($period->tax_year.'-01-01', $profile->registration_start_date->toDateString());
        $end = $period->period_end->toDateString();
        $reportFilters = ['start_date' => $start, 'end_date' => $end, 'as_of' => $end, 'fiscal_period_id' => null, 'report_view' => 'year_to_date', 'show_zero_balances' => false];
        $report = $this->incomeStatement->generate($reportFilters);
        if (bccomp((string) $report['reconciliation_difference'], '0.0000', 4) !== 0) {
            throw ValidationException::withMessages(['financial_report' => 'The cumulative income statement must reconcile before preparing 1701Q.']);
        }
        $summary = $report['summary'];
        $grossSales = (string) $summary['revenue'];
        $returns = (string) $summary['contra_revenue'];
        $netSales = (string) $summary['net_sales'];
        $costOfSales = (string) $summary['cost_of_sales'];
        $otherIncome = (string) $summary['other_income'];
        $grossIncome = bcadd(bcsub($netSales, $costOfSales, 4), $otherIncome, 4);
        $financialItemized = bcadd((string) $summary['operating_expenses'], (string) $summary['other_expenses'], 4);
        $deductionMethod = (string) $parameters['deduction_method'];
        $osd = $deductionMethod === 'osd' ? $this->percentage($this->osdBase($parameters, $grossSales, $netSales, $grossIncome), (string) $parameters['osd_rate']) : '0.0000';
        $manualDeduction = (string) ($data['manual_deduction_adjustment'] ?? '0');
        $taxableAdjustment = (string) ($data['taxable_income_adjustment'] ?? '0');
        $deductions = $deductionMethod === 'itemized' ? bcadd($financialItemized, $manualDeduction, 4) : bcadd($osd, $manualDeduction, 4);
        $taxable = bcadd(bcsub($grossIncome, $deductions, 4), $taxableAdjustment, 4);
        $taxable = bccomp($taxable, '0.0000', 4) < 0 ? '0.0000' : $taxable;
        $incomeTaxDue = $this->incomeTax($taxable, $parameters);
        $prior = $this->priorQuarter($obligation);
        $withholding = $this->withholdingSources($obligation, $start, $end);
        $verifiedWithholding = $this->sum($withholding);
        $priorPayments = (string) ($data['prior_quarter_payments'] ?? '0');
        $manualWithholding = (string) ($data['manual_creditable_withholding'] ?? '0');
        $otherCredits = (string) ($data['other_allowable_credits'] ?? '0');
        $surcharge = (string) ($data['surcharge'] ?? '0');
        $interest = (string) ($data['interest'] ?? '0');
        $compromise = (string) ($data['compromise_penalty'] ?? '0');
        $credits = bcadd(bcadd($priorPayments, $verifiedWithholding, 4), bcadd($manualWithholding, $otherCredits, 4), 4);
        $penalties = bcadd(bcadd($surcharge, $interest, 4), $compromise, 4);
        $payable = bcsub(bcadd($incomeTaxDue, $penalties, 4), $credits, 4);

        return [
            'taxable_year' => $period->tax_year, 'quarter' => $period->quarter, 'income_tax_method' => $profile->income_tax_option,
            'deduction_method' => $deductionMethod, 'cumulative_gross_sales' => $grossSales, 'sales_returns_discounts' => $returns,
            'net_sales' => $netSales, 'cost_of_sales' => $costOfSales, 'other_income' => $otherIncome, 'gross_income' => $grossIncome,
            'financial_itemized_deductions' => $financialItemized, 'osd_deduction' => $osd,
            'manual_deduction_adjustment' => $manualDeduction, 'taxable_income_adjustment' => $taxableAdjustment,
            'taxable_income' => $taxable, 'income_tax_due' => $incomeTaxDue,
            'prior_quarter_taxable_income' => $prior === null ? '0.0000' : $prior->taxable_income, 'prior_quarter_income_tax_due' => $prior === null ? '0.0000' : $prior->income_tax_due,
            'prior_quarter_payments' => $priorPayments, 'verified_creditable_withholding' => $verifiedWithholding,
            'manual_creditable_withholding' => $manualWithholding, 'other_allowable_credits' => $otherCredits,
            'surcharge' => $surcharge, 'interest' => $interest, 'compromise_penalty' => $compromise,
            'total_amount_payable' => bccomp($payable, '0.0000', 4) < 0 ? '0.0000' : $payable,
            'taxpayer_snapshot' => $this->taxpayerSnapshot($profile),
            'rule_snapshot' => $rule->only(['id', 'tax_type', 'bir_form_number', 'effective_from', 'effective_to', 'tax_base_rule', 'credit_rule', 'calculation_parameters', 'official_reference_title', 'official_reference_url', 'last_reviewed_on']),
            'financial_report_snapshot' => ['parameters' => $reportFilters, 'summary' => $summary, 'sections' => $this->reportSections($report['sections']), 'reconciliation_difference' => $report['reconciliation_difference']],
            'withholding_snapshot' => $withholding,
            'prior_quarter_snapshot' => $prior?->only(['id', 'revision_number', 'taxable_year', 'quarter', 'taxable_income', 'income_tax_due', 'total_amount_payable', 'frozen_at']),
            'manual_adjustment_reason' => $data['manual_adjustment_reason'] ?? null, 'manual_adjustment_evidence' => $data['manual_adjustment_evidence'] ?? null,
            'prior_payment_evidence' => $data['prior_payment_evidence'] ?? null, 'withholding_evidence' => $data['withholding_evidence'] ?? null,
            'other_credits_authority' => $data['other_credits_authority'] ?? null, 'other_credits_evidence' => $data['other_credits_evidence'] ?? null,
            'penalty_authority' => $data['penalty_authority'] ?? null, 'penalty_evidence' => $data['penalty_evidence'] ?? null,
            'preparation_notes' => $data['preparation_notes'] ?? null,
        ];
    }

    private function assertEligible(TaxObligation $obligation, ?Bir1701qWorksheet $previous, string $returnType): void
    {
        $profile = $obligation->taxPeriod->taxProfile;
        if ($obligation->bir_form_number !== '1701Q' || ! $profile->forms()->where('form_code', '1701Q')->where('active', true)->exists()) {
            throw ValidationException::withMessages(['tax_obligation_id' => 'The period is not registered and applicable for 1701Q.']);
        }
        if ($previous === null && $obligation->bir1701qWorksheets()->exists()) {
            throw ValidationException::withMessages(['tax_obligation_id' => 'A worksheet already exists. Create a revision instead.']);
        }
        if ($previous === null && $returnType !== 'original') {
            throw ValidationException::withMessages(['return_type' => 'The first worksheet revision must be an original return.']);
        }
        if ($previous !== null && ($previous->tax_obligation_id !== $obligation->id || $previous->frozen_at === null)) {
            throw ValidationException::withMessages(['revision' => 'Only a frozen worksheet for this obligation may be revised.']);
        }
        if ($returnType === 'amended' && ! $obligation->taxComplianceRule->amendment_supported) {
            throw ValidationException::withMessages(['return_type' => 'The effective rule does not support amended returns.']);
        }
    }

    /** @return array<string, mixed> */
    private function parameters(TaxObligation $obligation): array
    {
        $parameters = $obligation->taxComplianceRule->calculation_parameters;
        $option = $obligation->taxPeriod->taxProfile->income_tax_option;
        if (! is_array($parameters) || ! in_array($option, $parameters['supported_income_tax_options'] ?? [], true)) {
            throw ValidationException::withMessages(['income_tax_option' => 'The configured income-tax option is not explicitly supported by the effective 1701Q rule.']);
        }
        if (! in_array($parameters['deduction_method'] ?? null, ['itemized', 'osd'], true)) {
            throw ValidationException::withMessages(['calculation_parameters' => 'The effective rule must configure itemized or OSD deductions.']);
        }

        return $parameters;
    }

    /** @param array<string, mixed> $parameters */
    private function incomeTax(string $taxable, array $parameters): string
    {
        if (($parameters['computation_type'] ?? null) === 'flat_rate') {
            $base = bcsub($taxable, (string) ($parameters['exempt_threshold'] ?? '0'), 4);

            return $this->percentage(bccomp($base, '0.0000', 4) < 0 ? '0.0000' : $base, (string) $parameters['rate']);
        }
        if (($parameters['computation_type'] ?? null) !== 'graduated_brackets' || ! is_array($parameters['brackets'] ?? null)) {
            throw ValidationException::withMessages(['calculation_parameters' => 'The effective rule has no supported income-tax computation.']);
        }
        foreach ($parameters['brackets'] as $bracket) {
            $over = (string) $bracket['over'];
            $notOver = $bracket['not_over'] ?? null;
            if (bccomp($taxable, $over, 4) >= 0 && ($notOver === null || bccomp($taxable, (string) $notOver, 4) <= 0)) {
                return bcadd((string) $bracket['base_tax'], $this->percentage(bcsub($taxable, $over, 4), (string) $bracket['rate']), 4);
            }
        }

        throw ValidationException::withMessages(['calculation_parameters' => 'No configured tax bracket covers the cumulative taxable income.']);
    }

    /** @param array<string, mixed> $parameters */
    private function osdBase(array $parameters, string $grossSales, string $netSales, string $grossIncome): string
    {
        return match ($parameters['osd_base'] ?? null) {
            'gross_sales' => $grossSales, 'net_sales' => $netSales, 'gross_income' => $grossIncome,
            default => throw ValidationException::withMessages(['calculation_parameters' => 'The OSD base is not configured.']),
        };
    }

    private function priorQuarter(TaxObligation $obligation): ?Bir1701qWorksheet
    {
        return Bir1701qWorksheet::query()->where('taxable_year', $obligation->taxPeriod->tax_year)->where('quarter', '<', $obligation->taxPeriod->quarter)
            ->whereNotNull('frozen_at')->whereHas('taxObligation.taxPeriod', fn ($query) => $query->where('tax_profile_id', $obligation->taxPeriod->tax_profile_id))
            ->latest('quarter')->latest('revision_number')->first();
    }

    /** @return array<int, array{id: int, invoice_id: int<0, max>, certificate_number: string|null, certificate_date: string|null, amount: numeric-string, evidence: string|null}> */
    private function withholdingSources(TaxObligation $obligation, string $start, string $end): array
    {
        $deductions = GovernmentDeduction::query()->with(['applications' => fn ($query) => $query->where('tax_obligation_id', $obligation->id)])
            ->where('deduction_type', 'expanded_withholding_tax')->whereIn('status', [GovernmentDeductionStatus::Verified, GovernmentDeductionStatus::Applied])
            ->whereHas('applications', fn ($query) => $query->where('tax_obligation_id', $obligation->id))
            ->whereDate('covered_from', '<=', $end)->whereDate('covered_to', '>=', $start)->orderBy('certificate_date')->get();
        if ($deductions->contains(fn (GovernmentDeduction $deduction): bool => blank($deduction->certificate_number) || blank($deduction->attachment_reference))) {
            throw ValidationException::withMessages(['creditable_withholding' => 'Every included creditable-withholding record requires a certificate number and attachment evidence.']);
        }

        return $deductions->map(fn (GovernmentDeduction $deduction): array => ['id' => $deduction->id, 'invoice_id' => $deduction->sales_invoice_id, 'certificate_number' => $deduction->certificate_number, 'certificate_date' => $deduction->certificate_date?->toDateString(), 'amount' => $deduction->applications->sole()->amount, 'evidence' => $deduction->attachment_reference])->all();
    }

    /** @param iterable<array{amount: mixed}> $sources */
    private function sum(iterable $sources): string
    {
        $total = '0.0000';
        foreach ($sources as $source) {
            $total = bcadd($total, (string) $source['amount'], 4);
        }

        return $total;
    }

    private function percentage(string $amount, string $rate): string
    {
        return bcadd(bcdiv(bcmul($amount, $rate, 10), '100', 10), '0.00005', 4);
    }

    /** @param Collection<int, array<string, mixed>> $sections @return array<int, array<string, mixed>> */
    private function reportSections(Collection $sections): array
    {
        return $sections->map(fn (array $section): array => ['key' => $section['key'], 'label' => $section['label'], 'total' => $section['total'], 'accounts' => $section['rows']->map(fn (array $row): array => ['id' => $row['account']->id, 'code' => $row['account']->code, 'name' => $row['account']->name, 'amount' => $row['amount']])->all()])->values()->all();
    }

    /** @return array<string, mixed> */
    private function taxpayerSnapshot(TaxProfile $profile): array
    {
        $business = $profile->businessProfile;

        return ['tax_profile_id' => $profile->id, 'registered_business_name' => $business->registered_business_name, 'trade_name' => $business->trade_name, 'proprietor_name' => $business->proprietor_name, 'tin' => $profile->tin, 'branch_code' => $profile->branch_code, 'rdo_code' => $profile->rdo_code, 'registered_address' => $business->registered_address, 'taxpayer_type' => $profile->taxpayer_type, 'income_tax_option' => $profile->income_tax_option, 'registration_start_date' => $profile->registration_start_date->toDateString()];
    }
}
