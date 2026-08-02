<?php

namespace App\Services;

use App\Enums\AccountClass;
use App\Enums\AccountingSourceType;
use App\Enums\CustomerPaymentStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\SalesInvoiceStatus;
use App\Models\Account;
use App\Models\CustomerPayment;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\SalesInvoice;
use App\Models\TaxObligation;
use App\Models\TaxReconciliation;
use App\Models\TaxReconciliationAdjustment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesTaxReconciliation
{
    public const PERMISSIONS = ['tax-reconciliation.view', 'tax-reconciliation.adjust', 'tax-reconciliation.review', 'tax-reconciliation.export'];

    public function generate(TaxObligation $obligation, User $user): TaxReconciliation
    {
        return DB::transaction(function () use ($obligation, $user): TaxReconciliation {
            $period = $obligation->taxPeriod;
            $from = $period->capture_start->toDateString();
            $to = $period->period_end->toDateString();
            $invoices = SalesInvoice::query()->with(['customer:id,type', 'paymentAllocations.customerPayment:id,payment_date,status'])->whereBetween('invoice_date', [$from, $to])->orderBy('invoice_date')->orderBy('invoice_number')->get();
            $validInvoices = $invoices->reject(fn (SalesInvoice $invoice): bool => in_array($invoice->status, [SalesInvoiceStatus::Draft, SalesInvoiceStatus::Voided], true));
            $payments = CustomerPayment::query()->whereBetween('payment_date', [$from, $to])->whereNotIn('status', [CustomerPaymentStatus::Draft, CustomerPaymentStatus::Voided])->orderBy('payment_date')->get();
            $ledgerLines = $this->revenueLines($from, $to);
            $sequence = $this->sequenceIssues($invoices->whereNotNull('invoice_number'));
            $approvedAdjustments = (string) TaxReconciliationAdjustment::query()
                ->where('tax_reconciliation_id', $obligation->reconciliation?->id)->where('status', 'approved')->sum('amount');

            $gross = $this->sum($validInvoices, 'gross_amount');
            $credits = $this->sum($validInvoices, 'discount_amount');
            $operational = bcsub($gross, $credits, 4);
            $receipts = $this->sum($payments, 'gross_settlement_amount');
            $withholding = $this->sum($payments, 'withholding_amount');
            $ledgerCredits = $this->sum($ledgerLines, 'credit');
            $ledgerDebits = $this->sum($ledgerLines, 'debit');
            $ledgerRevenue = bcsub($ledgerCredits, $ledgerDebits, 4);
            $difference = bcsub(bcadd($operational, $approvedAdjustments, 4), $ledgerRevenue, 4);
            $unpostedInvoices = $invoices->where('status', SalesInvoiceStatus::Draft);
            $unpostedJournals = JournalEntry::query()->where('status', JournalEntryStatus::Draft)
                ->where('source_type', AccountingSourceType::SalesInvoice)->whereBetween('document_date', [$from, $to])->get(['id', 'journal_number', 'source_id']);
            $criticalCount = (bccomp($difference, '0.0000', 4) === 0 ? 0 : 1)
                + count($sequence['missing']) + count($sequence['duplicates']) + $unpostedInvoices->count() + $unpostedJournals->count();

            return TaxReconciliation::query()->updateOrCreate(['tax_obligation_id' => $obligation->id], [
                'tax_base_rule' => (string) data_get($obligation->rule_snapshot, 'tax_base_rule', 'No tax-base rule snapshot is available.'),
                'gross_sales' => $gross, 'credit_adjustments' => $credits, 'operational_net_sales' => $operational,
                'receipt_basis' => $receipts, 'ledger_revenue' => $ledgerRevenue, 'customer_withholding' => $withholding,
                'approved_adjustments' => $approvedAdjustments, 'difference' => $difference, 'critical_difference_count' => $criticalCount,
                'parameters' => ['capture_start' => $from, 'period_end' => $to, 'basis_views' => ['accrual', 'cash_receipt'], 'tax_base_rule' => data_get($obligation->rule_snapshot, 'tax_base_rule')],
                'source_snapshot' => [
                    'summary' => [
                        'government_sales' => $this->sum($validInvoices->filter(fn (SalesInvoice $invoice): bool => $invoice->customer->type === 'government'), 'net_sales_amount'),
                        'private_sales' => $this->sum($validInvoices->filter(fn (SalesInvoice $invoice): bool => $invoice->customer->type !== 'government'), 'net_sales_amount'),
                        'cash_sales' => $this->sum($validInvoices->filter(fn (SalesInvoice $invoice): bool => $this->isCashSale($invoice)), 'net_sales_amount'),
                        'credit_sales' => $this->sum($validInvoices->reject(fn (SalesInvoice $invoice): bool => $this->isCashSale($invoice)), 'net_sales_amount'),
                        'gross_by_date' => $validInvoices->groupBy(fn (SalesInvoice $invoice): string => $invoice->invoice_date->toDateString())->map(fn (Collection $dailyInvoices): string => $this->sum($dailyInvoices, 'gross_amount'))->all(),
                    ],
                    'issued_invoices' => $validInvoices->map(fn (SalesInvoice $invoice): array => $this->invoiceSnapshot($invoice))->values()->all(),
                    'voided_invoices' => $invoices->where('status', SalesInvoiceStatus::Voided)->map(fn (SalesInvoice $invoice): array => $this->invoiceSnapshot($invoice))->values()->all(),
                    'government_sales' => $validInvoices->filter(fn (SalesInvoice $invoice): bool => $invoice->customer->type === 'government')->pluck('id')->values()->all(),
                    'private_sales' => $validInvoices->filter(fn (SalesInvoice $invoice): bool => $invoice->customer->type !== 'government')->pluck('id')->values()->all(),
                    'cash_sales' => $validInvoices->filter(fn (SalesInvoice $invoice): bool => $this->isCashSale($invoice))->pluck('id')->values()->all(),
                    'credit_sales' => $validInvoices->reject(fn (SalesInvoice $invoice): bool => $this->isCashSale($invoice))->pluck('id')->values()->all(),
                    'collections' => $payments->map(fn (CustomerPayment $payment): array => ['id' => $payment->id, 'number' => $payment->payment_number, 'date' => $payment->payment_date->toDateString(), 'gross' => $payment->gross_settlement_amount, 'withholding' => $payment->withholding_amount, 'net_cash' => $payment->net_cash_received])->values()->all(),
                    'revenue_lines' => $ledgerLines->map(fn (JournalEntryLine $line): array => ['journal_entry_id' => $line->journal_entry_id, 'journal_number' => $line->journalEntry->journal_number, 'account_code' => $line->account->code, 'account_name' => $line->account->name, 'debit' => $line->debit, 'credit' => $line->credit])->values()->all(),
                    'invoice_sequence' => $sequence,
                    'unposted' => ['invoice_ids' => $unpostedInvoices->pluck('id')->values()->all(), 'journal_ids' => $unpostedJournals->pluck('id')->values()->all()],
                ],
                'generated_at' => now(), 'generated_by' => $user->id,
            ]);
        });
    }

    public function reviewAdjustment(TaxReconciliationAdjustment $adjustment, string $status, ?string $notes, User $user): TaxReconciliation
    {
        if ($adjustment->reviewer_id !== $user->id) {
            throw ValidationException::withMessages(['status' => 'Only the assigned reviewer may decide this adjustment.']);
        }
        if ($adjustment->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'This adjustment has already been reviewed.']);
        }
        $adjustment->update(['status' => $status, 'review_notes' => $notes, 'reviewed_at' => now(), 'reviewed_by' => $user->id]);

        return $this->generate($adjustment->reconciliation->taxObligation, $user);
    }

    /** @return Collection<int, JournalEntryLine> */
    private function revenueLines(string $from, string $to): Collection
    {
        return JournalEntryLine::query()->with(['journalEntry:id,journal_number', 'account:id,code,name'])
            ->whereIn('account_id', Account::query()->where('account_class', AccountClass::Income->value)->select('id'))
            ->whereIn('journal_entry_id', JournalEntry::query()->where('status', JournalEntryStatus::Posted)->whereBetween('journal_date', [$from, $to])->select('id'))
            ->get();
    }

    /**
     * @template T of object
     *
     * @param  Collection<int, T>  $records
     */
    private function sum(Collection $records, string $attribute): string
    {
        return $records->reduce(fn (string $total, object $record): string => bcadd($total, (string) data_get($record, $attribute, '0'), 4), '0.0000');
    }

    /** @param Collection<int, SalesInvoice> $invoices @return array{missing: list<string>, duplicates: list<string>} */
    private function sequenceIssues(Collection $invoices): array
    {
        $normalized = $invoices->pluck('invoice_number')->map(fn (string $number): string => mb_strtoupper(preg_replace('/[^A-Z0-9]/i', '', $number) ?? $number));
        $duplicates = $normalized->countBy()->filter(fn (int $count): bool => $count > 1)->keys()->values()->all();
        $groups = [];
        foreach ($invoices->pluck('invoice_number') as $number) {
            if (preg_match('/^(.*?)(\d+)$/', $number, $matches) !== 1) {
                continue;
            }
            $groups[$matches[1]][] = ['value' => (int) $matches[2], 'width' => mb_strlen($matches[2])];
        }
        $missing = [];
        foreach ($groups as $prefix => $numbers) {
            $values = array_column($numbers, 'value');
            if (count($values) < 2) {
                continue;
            }
            foreach (range(min($values), max($values)) as $value) {
                if (! in_array($value, $values, true)) {
                    $missing[] = $prefix.str_pad((string) $value, max(array_column($numbers, 'width')), '0', STR_PAD_LEFT);
                }
            }
        }

        return ['missing' => $missing, 'duplicates' => $duplicates];
    }

    /** @return array<string, mixed> */
    private function invoiceSnapshot(SalesInvoice $invoice): array
    {
        return ['id' => $invoice->id, 'number' => $invoice->invoice_number, 'date' => $invoice->invoice_date->toDateString(), 'customer_type' => $invoice->customer->type, 'gross' => $invoice->gross_amount, 'credit_adjustment' => $invoice->discount_amount, 'net_sales' => $invoice->net_sales_amount, 'status' => $invoice->status->value];
    }

    private function isCashSale(SalesInvoice $invoice): bool
    {
        $sameDayAllocations = $invoice->paymentAllocations->filter(function ($allocation) use ($invoice): bool {
            $payment = $allocation->customerPayment;

            return $allocation->status->value === 'active'
                && ! in_array($payment->status, [CustomerPaymentStatus::Draft, CustomerPaymentStatus::Voided], true)
                && $payment->payment_date->lte($invoice->invoice_date);
        });

        return bccomp($this->sum($sameDayAllocations, 'amount'), (string) $invoice->total_receivable, 4) >= 0;
    }
}
