<?php

namespace App\Services;

use App\Enums\GovernmentDeductionStatus;
use App\Models\Bir2551qWorksheet;
use App\Models\GovernmentDeduction;
use App\Models\TaxObligation;
use App\Models\TaxProfile;
use App\Models\TaxRateSetting;
use App\Models\TaxReconciliation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Bir2551qPreparation
{
    public const PERMISSIONS = ['bir-2551q.view', 'bir-2551q.prepare', 'bir-2551q.review', 'bir-2551q.approve', 'bir-2551q.revise', 'bir-2551q.export'];

    public function __construct(private TaxComplianceCalendar $calendar) {}

    /** @param array<string, mixed> $data */
    public function create(TaxObligation $obligation, array $data, User $user, ?Bir2551qWorksheet $previous = null): Bir2551qWorksheet
    {
        return DB::transaction(function () use ($obligation, $data, $user, $previous): Bir2551qWorksheet {
            $obligation->loadMissing(['taxPeriod.taxProfile.businessProfile', 'taxComplianceRule', 'reconciliation.adjustments']);
            $this->assertEligible($obligation, $previous);
            if ($previous === null && ($data['return_type'] ?? 'original') !== 'original') {
                throw ValidationException::withMessages(['return_type' => 'The first worksheet revision must be an original return.']);
            }
            if (($data['return_type'] ?? 'original') === 'amended' && ! $obligation->taxComplianceRule->amendment_supported) {
                throw ValidationException::withMessages(['return_type' => 'The effective rule does not support amended returns.']);
            }
            $revisionNumber = ((int) $obligation->bir2551qWorksheets()->max('revision_number')) + 1;

            return Bir2551qWorksheet::query()->create($this->worksheetData($obligation, $data) + [
                'tax_obligation_id' => $obligation->id,
                'tax_reconciliation_id' => $obligation->reconciliation->id,
                'previous_revision_id' => $previous?->id,
                'revision_number' => $revisionNumber,
                'return_type' => $data['return_type'] ?? 'original',
                'revision_reason' => $data['revision_reason'] ?? null,
                'prepared_by' => $user->id,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Bir2551qWorksheet $worksheet, array $data): Bir2551qWorksheet
    {
        if ($worksheet->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only draft 2551Q worksheets may be edited.']);
        }
        $worksheet->taxObligation->loadMissing(['taxPeriod.taxProfile.businessProfile', 'taxComplianceRule', 'reconciliation.adjustments']);
        $worksheet->update($this->worksheetData($worksheet->taxObligation, $data) + ['preparation_notes' => $data['preparation_notes'] ?? null]);

        return $worksheet->refresh();
    }

    public function submit(Bir2551qWorksheet $worksheet): void
    {
        if ($worksheet->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only draft worksheets may be submitted for review.']);
        }
        $worksheet->update(['status' => 'for_review', 'review_status' => 'pending']);
    }

    public function review(Bir2551qWorksheet $worksheet, User $user): void
    {
        if ($worksheet->status !== 'for_review') {
            throw ValidationException::withMessages(['status' => 'Only worksheets awaiting review may be reviewed.']);
        }
        $worksheet->update(['status' => 'reviewed', 'review_status' => 'reviewed', 'reviewed_at' => now(), 'reviewed_by' => $user->id]);
    }

    public function approve(Bir2551qWorksheet $worksheet, User $user): void
    {
        DB::transaction(function () use ($worksheet, $user): void {
            if ($worksheet->status !== 'reviewed') {
                throw ValidationException::withMessages(['status' => 'The worksheet must be reviewed before approval.']);
            }
            $this->assertReconciliationComplete($worksheet->taxObligation->reconciliation);
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
        $reconciliation = $obligation->reconciliation;
        $basis = (string) $data['basis_type'];
        $sources = $this->basisSources($reconciliation, $basis);
        $adjustmentSources = $reconciliation->adjustments->where('status', 'approved')->map(fn ($adjustment): array => [
            'key' => 'adjustment:'.$adjustment->id, 'type' => 'reconciliation_adjustment', 'reference' => $adjustment->evidence_reference,
            'date' => $adjustment->reviewed_at?->toDateString(), 'amount' => (string) $adjustment->amount,
        ]);
        $sources = $sources->concat($adjustmentSources)->values();
        $excludedKeys = collect($data['excluded_source_keys'] ?? [])->unique()->values();
        $unknownKeys = $excludedKeys->diff($sources->pluck('key'));
        if ($unknownKeys->isNotEmpty()) {
            throw ValidationException::withMessages(['excluded_source_keys' => 'An excluded source is not part of this reconciliation basis.']);
        }
        $included = $sources->reject(fn (array $source): bool => $excludedKeys->contains($source['key']))->values();
        $excluded = $sources->filter(fn (array $source): bool => $excludedKeys->contains($source['key']))->values();
        $gross = $this->sum($sources);
        $excludedAmount = $this->sum($excluded);
        $taxable = bcsub($gross, $excludedAmount, 4);
        $rate = $this->resolveRate($obligation);
        $grossTaxDue = $this->percentage($taxable, $rate['rate']);
        $withholdingSources = $this->governmentWithholdingSources($obligation, $included);
        $governmentWithheld = $this->sum($withholdingSources);
        $allowableCredits = (string) ($data['allowable_credits'] ?? '0');
        $priorPayment = (string) ($data['prior_payment'] ?? '0');
        $surcharge = (string) ($data['surcharge'] ?? '0');
        $interest = (string) ($data['interest'] ?? '0');
        $compromise = (string) ($data['compromise_penalty'] ?? '0');
        $penalties = bcadd(bcadd($surcharge, $interest, 4), $compromise, 4);
        $credits = bcadd(bcadd($allowableCredits, $governmentWithheld, 4), $priorPayment, 4);
        $net = bcsub(bcadd($grossTaxDue, $penalties, 4), $credits, 4);

        return [
            'basis_type' => $basis, 'return_year' => $period->tax_year, 'quarter' => $period->quarter,
            'gross_taxable_amount' => $gross, 'excluded_amount' => $excludedAmount, 'taxable_amount' => $taxable,
            'tax_rate' => $rate['rate'], 'gross_tax_due' => $grossTaxDue, 'allowable_credits' => $allowableCredits,
            'government_tax_withheld' => $governmentWithheld, 'prior_payment' => $priorPayment, 'surcharge' => $surcharge,
            'interest' => $interest, 'compromise_penalty' => $compromise,
            'total_amount_payable' => bccomp($net, '0.0000', 4) < 0 ? '0.0000' : $net,
            'taxpayer_snapshot' => $this->taxpayerSnapshot($profile),
            'rule_snapshot' => $rule->only(['id', 'tax_type', 'bir_form_number', 'form_title', 'effective_from', 'effective_to', 'tax_base_rule', 'credit_rule', 'official_reference_title', 'official_reference_url', 'last_reviewed_on']) + ['resolved_rate' => $rate],
            'reconciliation_snapshot' => ['id' => $reconciliation->id, 'generated_at' => $reconciliation->generated_at, 'difference' => $reconciliation->difference, 'critical_difference_count' => $reconciliation->critical_difference_count, 'customer_withholding' => $reconciliation->customer_withholding],
            'source_snapshot' => ['included' => $included->all(), 'excluded' => $excluded->all(), 'government_percentage_tax_withheld' => $withholdingSources->all()],
            'excluded_source_keys' => $excludedKeys->all(), 'exclusion_reason' => $data['exclusion_reason'] ?? null,
            'exclusion_evidence' => $data['exclusion_evidence'] ?? null, 'credits_authority' => $data['credits_authority'] ?? null,
            'credits_evidence' => $data['credits_evidence'] ?? null, 'prior_payment_reference' => $data['prior_payment_reference'] ?? null,
            'penalty_authority' => $data['penalty_authority'] ?? null, 'penalty_evidence' => $data['penalty_evidence'] ?? null,
            'preparation_notes' => $data['preparation_notes'] ?? null,
        ];
    }

    private function assertEligible(TaxObligation $obligation, ?Bir2551qWorksheet $previous): void
    {
        if ($obligation->bir_form_number !== '2551Q' || ! $obligation->taxPeriod->taxProfile->percentage_tax_registered) {
            throw ValidationException::withMessages(['tax_obligation_id' => 'The obligation is not an applicable registered 2551Q period.']);
        }
        $this->assertReconciliationComplete($obligation->reconciliation);
        if ($previous === null && $obligation->bir2551qWorksheets()->exists()) {
            throw ValidationException::withMessages(['tax_obligation_id' => 'A worksheet already exists. Create a revision instead.']);
        }
        if ($previous !== null && ($previous->tax_obligation_id !== $obligation->id || $previous->frozen_at === null)) {
            throw ValidationException::withMessages(['revision' => 'Only a frozen worksheet for this obligation may be revised.']);
        }
    }

    private function assertReconciliationComplete(?TaxReconciliation $reconciliation): void
    {
        if ($reconciliation === null || $reconciliation->critical_difference_count > 0) {
            throw ValidationException::withMessages(['reconciliation' => 'Complete the sales-tax reconciliation and resolve all critical differences first.']);
        }
    }

    /** @return Collection<int, array{key: string, type: string, reference: mixed, date: mixed, amount: string}> */
    private function basisSources(TaxReconciliation $reconciliation, string $basis): Collection
    {
        if ($basis === 'cash_receipt') {
            return collect(data_get($reconciliation->source_snapshot, 'collections', []))->map(fn (array $source): array => ['key' => 'collection:'.$source['id'], 'type' => 'collection', 'reference' => $source['number'], 'date' => $source['date'], 'amount' => (string) $source['gross']])->values();
        }

        return collect(data_get($reconciliation->source_snapshot, 'issued_invoices', []))->map(fn (array $source): array => ['key' => 'invoice:'.$source['id'], 'type' => 'sales_invoice', 'reference' => $source['number'], 'date' => $source['date'], 'amount' => (string) $source['net_sales']])->values();
    }

    private function governmentWithholdingSources(TaxObligation $obligation, Collection $included): Collection
    {
        $invoiceIds = $included->where('type', 'sales_invoice')->map(fn (array $source): int => (int) str($source['key'])->after(':')->toString());
        if ($invoiceIds->isEmpty()) {
            $invoiceIds = collect(data_get($obligation->reconciliation->source_snapshot, 'issued_invoices', []))->pluck('id');
        }

        return GovernmentDeduction::query()->where('deduction_type', 'percentage_tax_withheld')->where('status', GovernmentDeductionStatus::Verified)
            ->whereIn('sales_invoice_id', $invoiceIds)->whereDate('covered_from', '<=', $obligation->taxPeriod->period_end)
            ->whereDate('covered_to', '>=', $obligation->taxPeriod->capture_start)->orderBy('certificate_date')->get()
            ->map(fn (GovernmentDeduction $deduction): array => ['key' => 'government_deduction:'.$deduction->id, 'type' => 'percentage_tax_withheld', 'reference' => $deduction->certificate_number, 'date' => $deduction->certificate_date?->toDateString(), 'amount' => (string) $deduction->amount, 'evidence' => $deduction->attachment_reference]);
    }

    /** @return array{rate: numeric-string, source: string, source_id: int} */
    private function resolveRate(TaxObligation $obligation): array
    {
        $rule = $obligation->taxComplianceRule;
        $end = $obligation->taxPeriod->period_end->toDateString();
        if ($rule->effective_from->toDateString() <= $end && ($rule->effective_to === null || $rule->effective_to->toDateString() >= $end) && $rule->tax_rate !== null) {
            return ['rate' => $rule->tax_rate, 'source' => 'tax_compliance_rule', 'source_id' => $rule->id];
        }
        $setting = TaxRateSetting::query()->whereBelongsTo($obligation->taxPeriod->taxProfile)->where('tax_type', 'percentage_tax')->where('active', true)
            ->whereDate('effective_from', '<=', $end)->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $end))->latest('effective_from')->first();
        if ($setting === null) {
            throw ValidationException::withMessages(['tax_rate' => 'No percentage-tax rate is configured for the end of this tax period.']);
        }

        return ['rate' => $setting->rate, 'source' => 'tax_rate_setting', 'source_id' => $setting->id];
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

    /** @return numeric-string */
    private function percentage(string $amount, string $rate): string
    {
        $raw = bcdiv(bcmul($amount, $rate, 10), '100', 10);

        return bcadd($raw, '0.00005', 4);
    }

    /** @return array<string, mixed> */
    private function taxpayerSnapshot(TaxProfile $profile): array
    {
        $business = $profile->businessProfile;

        return ['tax_profile_id' => $profile->id, 'registered_business_name' => $business->registered_business_name, 'trade_name' => $business->trade_name, 'proprietor_name' => $business->proprietor_name, 'tin' => $profile->tin, 'branch_code' => $profile->branch_code, 'rdo_code' => $profile->rdo_code, 'registered_address' => $business->registered_address, 'taxpayer_type' => $profile->taxpayer_type, 'vat_status' => $profile->vat_status, 'registration_start_date' => $profile->registration_start_date->toDateString()];
    }
}
