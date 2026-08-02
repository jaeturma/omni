<?php

namespace App\Services;

use App\Enums\GovernmentDeductionStatus;
use App\Models\GovernmentDeduction;
use App\Models\SalesInvoice;
use App\Models\TaxObligation;
use App\Models\User;
use App\Models\WithholdingCertificateApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithholdingCertificateReconciliation
{
    public const PERMISSIONS = ['withholding-certificates.view', 'withholding-certificates.create', 'withholding-certificates.verify', 'withholding-certificates.apply', 'withholding-certificates.reject', 'withholding-certificates.void', 'withholding-reconciliation.export'];

    /** @param array<string, mixed> $data */
    public function apply(GovernmentDeduction $certificate, TaxObligation $obligation, array $data, User $user): WithholdingCertificateApplication
    {
        return DB::transaction(function () use ($certificate, $obligation, $data, $user): WithholdingCertificateApplication {
            $locked = GovernmentDeduction::query()->lockForUpdate()->findOrFail($certificate->id);
            if (! in_array($locked->status, [GovernmentDeductionStatus::Verified, GovernmentDeductionStatus::Applied], true)) {
                throw ValidationException::withMessages(['certificate' => 'Only verified certificates may be applied.']);
            }
            if (WithholdingCertificateApplication::query()->whereBelongsTo($locked, 'certificate')->whereBelongsTo($obligation)->exists()) {
                throw ValidationException::withMessages(['tax_obligation_id' => 'This certificate is already applied to the selected return.']);
            }
            $amount = (string) $data['amount'];
            $remaining = $locked->remainingAmount();
            if (bccomp($amount, '0.0000', 4) <= 0 || bccomp($amount, $remaining, 4) > 0) {
                throw ValidationException::withMessages(['amount' => 'Application amount exceeds the certificate remaining balance.']);
            }
            $parameters = $obligation->taxComplianceRule->calculation_parameters ?? [];
            if (bccomp($amount, $remaining, 4) < 0 && ! ($parameters['allow_partial_withholding_application'] ?? false)) {
                throw ValidationException::withMessages(['amount' => 'The effective return rule does not allow partial certificate application.']);
            }
            $application = WithholdingCertificateApplication::query()->create([
                'government_deduction_id' => $locked->id, 'tax_obligation_id' => $obligation->id, 'amount' => $amount,
                'evidence_reference' => $data['evidence_reference'], 'notes' => $data['notes'] ?? null, 'applied_by' => $user->id, 'applied_at' => now(),
            ]);
            if (bccomp($locked->remainingAmount(), '0.0000', 4) === 0) {
                $locked->update(['status' => GovernmentDeductionStatus::Applied, 'updated_by' => $user->id]);
            }

            return $application;
        }, 3);
    }

    /** @return array<string, mixed> */
    public function summary(int $year): array
    {
        $certificates = GovernmentDeduction::query()->with(['customer:id,name', 'salesInvoice:id,invoice_number', 'journalEntryLine:id,journal_entry_id,debit,credit', 'journalEntryLine.journalEntry:id,status', 'applications:id,government_deduction_id,tax_obligation_id,amount'])
            ->whereYear('covered_to', $year)->whereIn('deduction_type', ['expanded_withholding_tax', 'percentage_tax_withheld'])->where('status', '!=', GovernmentDeductionStatus::Voided)->latest('covered_to')->get();
        $missing = SalesInvoice::query()->with('customer:id,name')->whereYear('invoice_date', $year)->where('expected_withholding_amount', '>', 0)
            ->whereIn('status', ['posted', 'partially_paid', 'paid', 'overdue'])->whereDoesntHave('governmentDeductions', fn ($query) => $query->where('status', '!=', GovernmentDeductionStatus::Voided))->latest('invoice_date')->get();
        $certificateTotal = $certificates->whereIn('status', [GovernmentDeductionStatus::Verified, GovernmentDeductionStatus::Applied])->reduce(fn (string $sum, GovernmentDeduction $row): string => bcadd($sum, $row->amount, 4), '0.0000');
        $ledgerTotal = $certificates->reduce(function (string $sum, GovernmentDeduction $row): string {
            $line = $row->journalEntryLine;

            return $line?->journalEntry->status->value === 'posted' ? bcadd($sum, bcsub($line->debit, $line->credit, 4), 4) : $sum;
        }, '0.0000');

        return ['certificates' => $certificates, 'missing' => $missing, 'certificate_total' => $certificateTotal, 'ledger_total' => $ledgerTotal,
            'difference' => bcsub($certificateTotal, $ledgerTotal, 4), 'unapplied_count' => $certificates->filter(fn (GovernmentDeduction $row): bool => in_array($row->status, [GovernmentDeductionStatus::Verified, GovernmentDeductionStatus::Applied], true) && bccomp($row->remainingAmount(), '0.0000', 4) > 0)->count()];
    }
}
