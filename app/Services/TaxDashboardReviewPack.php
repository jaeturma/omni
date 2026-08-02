<?php

namespace App\Services;

use App\Models\BusinessProfile;
use App\Models\DocumentNumberReservation;
use App\Models\FiscalPeriod;
use App\Models\GovernmentDeduction;
use App\Models\SourcePosting;
use App\Models\TaxPeriod;
use App\Models\TaxReconciliation;
use Carbon\CarbonImmutable;

class TaxDashboardReviewPack
{
    public const PERMISSIONS = ['tax-dashboard.view', 'tax-review-pack.generate', 'tax-review-pack.download', 'tax-review-comments.manage'];

    /** @return array<string, mixed> */
    public function build(TaxPeriod $period, bool $revealSensitive = false): array
    {
        $period->load(['taxProfile.businessProfile', 'obligations.taxComplianceRule', 'obligations.reconciliation', 'obligations.bir2551qWorksheets', 'obligations.bir1701qWorksheets', 'obligations.taxFilings.payments', 'obligations.taxFilings.attachments', 'reviewComments.author:id,name', 'reviewComments.resolver:id,name']);
        $obligations = $period->obligations;
        $today = CarbonImmutable::today();
        $critical = (int) TaxReconciliation::query()->whereIn('tax_obligation_id', $obligations->pluck('id'))->sum('critical_difference_count');
        $range = [$period->capture_start->toDateString().' 00:00:00', $period->period_end->toDateString().' 23:59:59'];
        $deductions = GovernmentDeduction::query()->whereBetween('covered_from', [$period->capture_start, $period->period_end]);
        $missingCertificates = (clone $deductions)->whereIn('deduction_type', ['percentage_tax_withheld', 'expanded_withholding_tax'])->where(fn ($query) => $query->whereNull('certificate_number')->orWhereNull('attachment_reference'))->count();
        $missingProof = $obligations->sum(fn ($obligation): int => $obligation->taxFilings->sum(function ($filing): int {
            $types = $filing->attachments->pluck('attachment_type');

            return ($types->contains('proof_of_filing') ? 0 : 1) + ($filing->payments->isNotEmpty() && ! $types->contains('proof_of_payment') ? 1 : 0);
        }));
        $unreconciled = $obligations->filter(fn ($obligation): bool => $obligation->reconciliation === null || $obligation->reconciliation->critical_difference_count > 0)->count();
        $status = fn (string $value): int => $obligations->where('status', $value)->count();
        $indicators = [
            'upcoming' => $obligations->filter(fn ($obligation): bool => $obligation->effectiveDueDate()->gt($today->addDays(30)) && $obligation->filing_status !== 'filed')->count(),
            'due_soon' => $obligations->filter(fn ($obligation): bool => $obligation->effectiveDueDate()->betweenIncluded($today, $today->addDays(30)) && $obligation->filing_status !== 'filed')->count(),
            'overdue' => $obligations->filter(fn ($obligation): bool => $obligation->effectiveDueDate()->lt($today) && $obligation->filing_status !== 'filed')->count(),
            'preparing' => $status('preparing'), 'for_review' => $status('for_review'), 'ready_to_file' => $critical > 0 ? 0 : $status('ready_to_file'),
            'filed_but_unpaid' => $obligations->where('filing_status', 'filed')->whereIn('payment_status', ['unpaid', 'partially_paid'])->count(),
            'paid' => $obligations->whereIn('payment_status', ['paid', 'overpaid'])->count(), 'amended' => $obligations->where('amendment_status', 'amended')->count(),
            'missing_certificates' => $missingCertificates, 'unreconciled_sales' => $unreconciled,
            'invoice_sequence_gaps' => $this->invoiceSequenceGaps($period, $range),
            'failed_accounting_postings' => SourcePosting::query()->where('status', 'failed')->whereBetween('last_attempt_at', $range)->count(),
            'unclosed_periods' => FiscalPeriod::query()->whereDate('starts_on', '<=', $period->period_end)->whereDate('ends_on', '>=', $period->capture_start)->where('status', 'open')->count(),
            'missing_filing_or_payment_proof' => $missingProof,
        ];
        $profile = $period->taxProfile;
        $business = $profile->businessProfile;

        return ['period' => $period, 'obligations' => $obligations, 'indicators' => $indicators, 'critical_blocker' => $critical > 0,
            'last_refresh' => $obligations->pluck('reconciliation.generated_at')->filter()->max(), 'last_rule_review' => $obligations->pluck('taxComplianceRule.last_reviewed_on')->filter()->max(),
            'taxpayer' => ['registered_name' => $business->registered_business_name, 'trade_name' => $business->trade_name,
                'tin' => $revealSensitive ? $profile->tin : $this->mask($profile->tin), 'branch_code' => $revealSensitive ? $profile->branch_code : $this->mask($profile->branch_code),
                'rdo_code' => $profile->rdo_code, 'registration_type' => $profile->registration_type, 'vat_status' => $profile->vat_status,
                'income_tax_option' => $profile->income_tax_option, 'registered_books_type' => $profile->registered_books_type],
            'withholding' => ['count' => (clone $deductions)->count(), 'verified_amount' => (string) (clone $deductions)->where('status', 'verified')->sum('amount')],
            'schedule_index' => BooksAndSchedules::REPORTS, 'comments' => $period->reviewComments,
            'unresolved_issues' => collect($indicators)->filter(fn (int $count, string $key): bool => $count > 0 && in_array($key, ['overdue', 'missing_certificates', 'unreconciled_sales', 'invoice_sequence_gaps', 'failed_accounting_postings', 'unclosed_periods', 'missing_filing_or_payment_proof'], true)),
            'generated_at' => now(), 'disclaimer' => 'Preparation and review support only. Omni does not file returns or make tax payments through BIR.'];
    }

    /** @param array{0: string, 1: string} $range */
    private function invoiceSequenceGaps(TaxPeriod $period, array $range): int
    {
        $business = BusinessProfile::query()->active()->first();
        if ($business === null) {
            return 0;
        }
        $numbers = DocumentNumberReservation::query()->whereBetween('issued_at', $range)
            ->whereIn('document_sequence_id', $business->documentSequences()->where('document_type', 'sales_invoice')->select('id'))->orderBy('number')->pluck('number');

        return $numbers->isEmpty() ? 0 : max(0, ((int) $numbers->last() - (int) $numbers->first() + 1) - $numbers->count());
    }

    private function mask(?string $value): string
    {
        return blank($value) ? 'Not configured' : str_repeat('*', max(0, mb_strlen($value) - 4)).mb_substr($value, -4);
    }
}
